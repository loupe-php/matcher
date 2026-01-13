<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Locale;

abstract class AbstractBinaryFileDictionary implements DictionaryInterface
{
    private const FILE_NAME_INDEX = 'index';

    private const FILE_NAME_PREFIX2_BUCKETS = 'prefix_buckets_2';

    private const FILE_NAME_PREFIX34_BUCKETS = 'prefix_buckets_34';

    private const FILE_NAME_TERMS = 'terms';

    /**
     * Maximum term length (in bytes) supported by the binary dictionary format.
     *
     * Terms longer than this limit are ignored during dictionary construction.
     * This upper bound exists because prefix compression and refinement logic
     * rely on representing prefix lengths and suffix lengths in a single byte.
     *
     * A limit of 256 bytes is sufficient for all realistic dictionary terms
     * and keeps the on-disk format simple, compact, and fast to decode.
     */
    private const MAX_LENGTH_IN_BYTES = 256;

    /**
     * Maximum bucket size before deeper prefix refinement is applied.
     *
     * Buckets whose size exceeds this threshold are further subdivided using
     * sparse prefix refinement (prefix length 3 and optionally 4).
     *
     * The value is chosen as a balance between:
     *  - minimizing the number of string comparisons during binary search
     *  - keeping refinement tables small and sparse
     *
     * A threshold of 512 ensures that even in the worst case, lookup requires
     * only a small, cache-friendly binary search while avoiding excessive
     * refinement depth or file size.
     */
    private const PREFIX34_THRESHOLD = 512;

    /**
     * Placeholder value representing an invalid or non-existent index.
     *
     * All term positions and bucket bounds in this dictionary are stored as
     * unsigned 32-bit indices that refer to positions within the term index.
     * The maximum valid index is therefore `(termCount - 1)`, which is always
     * strictly less than `2^32 - 1`.
     *
     * The value `0xFFFFFFFF` (2^32 - 1) can thus never represent a valid index
     * and is safe to use as a placeholder for “no entry / no bucket”.
     *
     * Using this value allows the binary index files to remain compact and
     * self-contained without additional presence flags.
     */
    private const U32_NONE = 0xFFFFFFFF;

    private string $blob = '';

    private int $count = 0;

    private string $index = '';

    /**
     * 2-byte dense (!) prefix buckets.
     *
     * This table splits the entire term index into at most 65,536 buckets,
     * addressed directly by the first two bytes of a term.
     *
     * In practice, only a small fraction of these buckets will ever be used:
     * a natural language dictionary cannot contain terms for all possible
     * byte combinations, and many prefixes are linguistically impossible.
     *
     * The dense layout is intentional: it allows O(1) bucket lookup via
     * direct addressing (`key * 8`) without any search or hashing overhead.
     *
     * Buckets can still be large for common prefixes (e.g. "st", "ge", "sch" for German),
     * which is why deeper, sparse (!) refinement buckets are applied optionally
     * on top of this structure.
     */
    private string $prefix2Buckets = '';

    /**
     * Sparse prefix refinement buckets for deeper lookups (prefix length 3 and 4).
     *
     * This file contains only refinement tables for prefixes that are still
     * too large after the initial 2-byte split. Each table further subdivides
     * an existing bucket by the next byte of the term.
     *
     * Unlike the 2-byte dense structure, this structure is intentionally sparse (!):
     * tables are emitted only for prefixes whose bucket size exceeds the
     * configured threshold. This keeps the file small while still reducing
     * the worst-case bucket size dramatically.
     *
     * Multiple refinement depths are stored in the same file:
     *  - level 3 tables split a 2-byte prefix by the 3rd byte
     *  - level 4 tables split a 3-byte prefix by the 4th byte
     */
    private string $prefix34Buckets = '';

    /**
     * Lookup index for `$prefix34Buckets`.
     *
     * This array maps a prefix identifier to the byte offset of its corresponding
     * refinement table inside the `$prefix34Buckets` binary blob.
     *
     * Structure:
     *  - `$prefix34Index[3][key2] = offset`
     *      - `key2` is the 2-byte prefix (first two bytes of the term)
     *      - the table at `offset` splits this prefix by the 3rd byte
     *
     *  - `$prefix34Index[4][key3] = offset`
     *      - `key3` is the 3-byte prefix (first three bytes of the term, packed)
     *      - the table at `offset` splits this prefix by the 4th byte
     *
     * @var array<int, array<int, int>>
     */
    private array $prefix34Index = [];

    /**
     * @param array<string> $dictionary
     */
    protected function __construct(
        protected Locale $locale,
        protected array $dictionary = []
    ) {

    }

    public function add(string $term): void
    {
        if ($this->blob !== '') {
            throw new \LogicException('This dictionary is readonly.');
        }

        $this->dictionary[] = $term;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function has(string $term): bool
    {
        if ($this->blob === '') {
            throw new \LogicException('This dictionary is not initalized yet.');
        }

        // Use the first two bytes of the term to search in our prefix index for matching bounds.
        $byte0 = isset($term[0]) ? \ord($term[0]) : 0;
        $byte1 = isset($term[1]) ? \ord($term[1]) : 0;
        $key2 = ($byte0 << 8) | $byte1;

        $bounds = $this->prefix2Bounds($key2);
        if ($bounds === null) {
            return false;
        }

        [$low, $high] = $bounds;

        if (!$this->refineBoundsWithPrefix34($term, $key2, $low, $high)) {
            return false;
        }

        return $this->binarySearchInBounds($term, $low, $high);
    }

    protected function doWrite(string $pathToDirectory): void
    {
        if (!is_dir($pathToDirectory)) {
            throw new \InvalidArgumentException("{$pathToDirectory} is not a directory");
        }

        // Make sure, the dictionary contains unique terms only
        $this->dictionary = array_values(array_unique($this->dictionary));

        // Sort the dictionary, shortest first (for prefix compression)
        sort($this->dictionary, SORT_STRING);

        // Level 9 compression for best results (time is not relevant here)
        $out = gzopen($pathToDirectory . '/dictionary.gz', 'wb9');
        if ($out === false) {
            throw new \RuntimeException('Cannot open directory.gz for writing');
        }

        $prev = '';

        foreach ($this->dictionary as $term) {
            // Ignore terms longer than 256 bytes because for those we cannot generate a char representation
            // (also they aren't  suitable for dictionary based compound word handling anyway)
            if (\strlen($term) > self::MAX_LENGTH_IN_BYTES) {
                continue;
            }

            $prefixLength = self::calculateCommonPrefixLengthInBytes($prev, $term);
            $suffix = substr($term, $prefixLength);

            // Now we write our format which is: [1 byte, prefix length][1 byte, suffix length][suffix]
            // Because lots of terms share a common prefix, this will compress the file quite a bit, and it is
            // a very easy algorithm to work with.
            gzwrite($out, \chr($prefixLength) . \chr(\strlen($suffix)) . $suffix);

            $prev = $term;
        }

        gzclose($out);
    }

    protected function loadFromDirectory(string $directory): void
    {
        $termsPath = $directory . '/' . self::FILE_NAME_TERMS;
        $indexPath = $directory . '/' . self::FILE_NAME_INDEX;
        $prefix2Path = $directory . '/' . self::FILE_NAME_PREFIX2_BUCKETS;
        $prefix34Path = $directory . '/' . self::FILE_NAME_PREFIX34_BUCKETS;
        $blob = null;
        $index = null;
        $prefix2Buckets = null;
        $prefix34Buckets = null;

        $load = function (bool $decode = true) use (&$load, $directory, $termsPath, $indexPath, $prefix2Path, $prefix34Path, &$blob, &$index, &$prefix2Buckets, &$prefix34Buckets) {
            $blob = @file_get_contents($termsPath);
            $index = @file_get_contents($indexPath);
            $prefix2Buckets = @file_get_contents($prefix2Path);
            $prefix34Buckets = @file_get_contents($prefix34Path);

            if ($decode && ($blob === false || $index === false || $prefix2Buckets === false)) {
                $this->decodeDictionary($directory);
                $load(false);
            }
        };
        $load();

        if (!\is_string($blob) || !\is_string($index) || !\is_string($prefix2Buckets)) {
            throw new \RuntimeException('Cannot load blob from directory.');
        }

        $this->blob = $blob;
        $this->index = $index;
        $this->count = intdiv(\strlen($index), 4);
        $this->prefix2Buckets = $prefix2Buckets;

        // Optional prefix3+prefix4 buckets in one sparse file
        $this->prefix34Buckets = \is_string($prefix34Buckets) ? $prefix34Buckets : '';
        $this->prefix34Index = [];
        if ($this->prefix34Buckets === '') {
            return;
        }

        // Block format (repeated):
        // [u8 level][u32 key][256 * (u32 low, u32 high)]
        $blockHeaderSize = 5;
        $tableSizeBytes = 256 * 8;

        $blockOffset = 0;
        $blobLength = \strlen($this->prefix34Buckets);

        while ($blockOffset + $blockHeaderSize + $tableSizeBytes <= $blobLength) {
            $level = \ord($this->prefix34Buckets[$blockOffset]);

            $rawKey = unpack('Vkey', substr($this->prefix34Buckets, $blockOffset + 1, 4))['key'] ?? 0;
            $tableOffset = $blockOffset + $blockHeaderSize;

            if ($level === 3) {
                // key2 stored in the low 16 bits
                $key2 = $rawKey & 0xFFFF;
                $this->prefix34Index[3][$key2] = $tableOffset;
            } elseif ($level === 4) {
                // key3 stored in the low 24 bits
                $key3 = $rawKey & 0xFFFFFF;
                $this->prefix34Index[4][$key3] = $tableOffset;
            }

            $blockOffset = $tableOffset + $tableSizeBytes;
        }
    }

    /**
     * Applies a 256-entry refinement table at $tableStart to narrow [low, high] using $byte.
     * Returns false if that byte has no bucket.
     */
    private function applyRefinementTable(int $tableStart, int $byte, int &$low, int &$high): bool
    {
        $offset = $tableStart + ($byte * 8);
        $bucket = unpack('Vlow/Vhigh', substr($this->prefix34Buckets, $offset, 8));
        $newLow = $bucket['low'] ?? self::U32_NONE;

        if ($newLow === self::U32_NONE) {
            return false;
        }

        $low = (int) $newLow;
        $high = (int) ($bucket['high'] ?? -1);

        return true;
    }

    private function binarySearchInBounds(string $term, int $low, int $high): bool
    {
        while ($low <= $high) {
            $mid = ($low + $high) >> 1;

            $currentTerm = $this->termAt($mid);
            $cmp = strcmp($term, $currentTerm);

            if ($cmp === 0) {
                return true;
            }

            if ($cmp < 0) {
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        return false;
    }

    /**
     * Builds one 256-entry table payload: 256 * (u32 low, u32 high).
     *
     * @param array<int, array{0:int,1:int}> $rangesByByte
     */
    private function build256Table(array $rangesByByte): string
    {
        $buf = '';
        for ($b = 0; $b < 256; $b++) {
            if (!isset($rangesByByte[$b])) {
                $buf .= pack('V', self::U32_NONE) . pack('V', self::U32_NONE);
            } else {
                [$l, $h] = $rangesByByte[$b];
                $buf .= pack('V', $l) . pack('V', $h);
            }
        }

        return $buf;
    }

    private static function calculateCommonPrefixLengthInBytes(string $a, string $b): int
    {
        $max = min(\strlen($a), \strlen($b));
        for ($i = 0; $i < $max; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $i;
            }
        }

        return $max;
    }

    private function decodeDictionary(string $directory): void
    {
        $directoryHandle = gzopen($directory . '/dictionary.gz', 'rb');

        if ($directoryHandle === false) {
            throw new \RuntimeException('Cannot open dictionary.gz for reading');
        }

        $termsOut = $this->openFileHandleForWriting($directory, self::FILE_NAME_TERMS);
        $indexOut = $this->openFileHandleForWriting($directory, self::FILE_NAME_INDEX);
        $prefix2BucketsOut = $this->openFileHandleForWriting($directory, self::FILE_NAME_PREFIX2_BUCKETS);
        $prefix34BucketsOut = $this->openFileHandleForWriting($directory, self::FILE_NAME_PREFIX34_BUCKETS);

        $previous = '';
        $position = 0;
        $prefix2Ranges = [];
        $prefix3Ranges = [];
        $prefix4Ranges = [];
        $termIndex = 0;

        while (!gzeof($directoryHandle)) {
            $term = $this->readNextTermFromGz($directoryHandle, $previous);
            if ($term === null) {
                break;
            }

            // Our index file contains all positions as unsigned 32-bit integer (should be easily enough space)
            fwrite($indexOut, pack('V', $position));
            fwrite($termsOut, $term . "\n");

            $this->trackPrefixRanges($term, $termIndex, $prefix2Ranges, $prefix3Ranges, $prefix4Ranges);

            $termIndex++;
            $position += \strlen($term) + 1;
            $previous = $term;
        }

        $this->writePrefix2Buckets($prefix2BucketsOut, $prefix2Ranges);
        $this->writePrefix34Buckets($prefix34BucketsOut, $prefix2Ranges, $prefix3Ranges, $prefix4Ranges);

        fclose($prefix2BucketsOut);
        fclose($prefix34BucketsOut);
        fclose($termsOut);
        fclose($indexOut);
        gzclose($directoryHandle);
    }

    /**
     * @param resource $gzipHandle
     */
    private function gzReadBytesExactly($gzipHandle, int $byteCount): ?string
    {
        if ($byteCount <= 0) {
            return '';
        }

        $resultBuffer = '';
        $totalBytesRead = 0;

        while ($totalBytesRead < $byteCount && !gzeof($gzipHandle)) {
            $remainingBytes = $byteCount - $totalBytesRead;
            $bytesToRead = min(8192, $remainingBytes); // Prevent huge allocations by limiting the maximum bytes to read

            $dataChunk = gzread($gzipHandle, $bytesToRead);
            if ($dataChunk === false || $dataChunk === '') {
                break;
            }

            $chunkLength = \strlen($dataChunk);

            $resultBuffer .= $dataChunk;
            $totalBytesRead += $chunkLength;
        }

        return ($totalBytesRead === $byteCount) ? $resultBuffer : null;
    }

    private function offsetAt(int $index): int
    {
        return unpack('Voffset', $this->index, $index * 4)['offset'] ?? 0;
    }

    /**
     * @return resource
     */
    private function openFileHandleForWriting(string $directory, string $filename)
    {
        $handle = fopen($directory . '/' . $filename, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(\sprintf('Cannot open "%s" for writing.', $filename));
        }

        return $handle;
    }

    /**
     * Returns the [low, high] bounds for the 2-byte prefix bucket, or null if no such bucket exists.
     *
     * @return array{0:int,1:int}|null
     */
    private function prefix2Bounds(int $key2): ?array
    {
        $bucket = unpack('Vlow/Vhigh', substr($this->prefix2Buckets, $key2 * 8, 8));
        $low = $bucket['low'] ?? self::U32_NONE;

        if ($low === self::U32_NONE) {
            return null;
        }

        $high = $bucket['high'] ?? -1;

        return [(int) $low, (int) $high];
    }

    /**
     * @param resource $gzipHandle
     */
    private function readNextTermFromGz($gzipHandle, string $previous): ?string
    {
        $header = $this->gzReadBytesExactly($gzipHandle, 2);
        if ($header === null) {
            return null;
        }

        $prefixLen = \ord($header[0]);
        $suffixLen = \ord($header[1]);

        $suffix = $this->gzReadBytesExactly($gzipHandle, $suffixLen);
        if ($suffix === null) {
            throw new \RuntimeException(\sprintf('Gzipped file is incorrect. Expected %d bytes but got none.', $suffixLen));
        }

        return substr($previous, 0, $prefixLen) . $suffix;
    }

    /**
     * Narrows the given bounds using optional sparse prefix3/prefix4 tables.
     * Returns false if refinement proves the term cannot exist.
     */
    private function refineBoundsWithPrefix34(string $term, int $key2, int &$low, int &$high): bool
    {
        if ($this->prefix34Buckets === '' || ($high - $low + 1) <= self::PREFIX34_THRESHOLD) {
            return true;
        }

        // Level 3: key2 -> split by 3rd byte
        if (isset($this->prefix34Index[3][$key2])) {
            $byte2 = isset($term[2]) ? \ord($term[2]) : 0;

            if (!$this->applyRefinementTable($this->prefix34Index[3][$key2], $byte2, $low, $high)) {
                return false;
            }
        }

        // Level 4: key3 -> split by 4th byte (only if still large)
        if (($high - $low + 1) <= self::PREFIX34_THRESHOLD) {
            return true;
        }

        $byte2 = isset($term[2]) ? \ord($term[2]) : 0;
        $key3 = ($key2 << 8) | $byte2;

        if (!isset($this->prefix34Index[4][$key3])) {
            return true;
        }

        $byte3 = isset($term[3]) ? \ord($term[3]) : 0;

        return $this->applyRefinementTable($this->prefix34Index[4][$key3], $byte3, $low, $high);
    }

    private function termAt(int $index): string
    {
        $start = $this->offsetAt($index);
        $end = ($index + 1 < $this->count) ? $this->offsetAt($index + 1) : \strlen($this->blob);

        return rtrim(substr($this->blob, $start, $end - $start), "\n");
    }

    /**
     * Updates prefix range tracking structures for one term.
     *
     * @param array<int, array{0:int,1:int}> $prefix2Ranges
     * @param array<int, array<int, array{0:int,1:int}>> $prefix3Ranges
     * @param array<int, array<int, array{0:int,1:int}>> $prefix4Ranges
     */
    private function trackPrefixRanges(string $term, int $termIndex, array &$prefix2Ranges, array &$prefix3Ranges, array &$prefix4Ranges): void
    {
        $byte0 = isset($term[0]) ? \ord($term[0]) : 0;
        $byte1 = isset($term[1]) ? \ord($term[1]) : 0;
        $key2 = ($byte0 << 8) | $byte1;

        if (!isset($prefix2Ranges[$key2])) {
            $prefix2Ranges[$key2] = [$termIndex, $termIndex];
        } else {
            $prefix2Ranges[$key2][1] = $termIndex;
        }

        $byte2 = isset($term[2]) ? \ord($term[2]) : 0;
        if (!isset($prefix3Ranges[$key2][$byte2])) {
            $prefix3Ranges[$key2][$byte2] = [$termIndex, $termIndex];
        } else {
            $prefix3Ranges[$key2][$byte2][1] = $termIndex;
        }

        $byte3 = isset($term[3]) ? \ord($term[3]) : 0;
        $key3 = ($key2 << 8) | $byte2;
        if (!isset($prefix4Ranges[$key3][$byte3])) {
            $prefix4Ranges[$key3][$byte3] = [$termIndex, $termIndex];
        } else {
            $prefix4Ranges[$key3][$byte3][1] = $termIndex;
        }
    }

    /**
     * @param resource $out
     * @param array<int, array{0:int,1:int}> $prefix2Ranges
     */
    private function writePrefix2Buckets($out, array $prefix2Ranges): void
    {
        for ($k = 0; $k < 65536; $k++) {
            if (!isset($prefix2Ranges[$k])) {
                fwrite($out, pack('V', self::U32_NONE) . pack('V', self::U32_NONE));
            } else {
                [$low, $high] = $prefix2Ranges[$k];
                fwrite($out, pack('V', $low) . pack('V', $high));
            }
        }
    }

    /**
     * Writes sparse refinement buckets for prefix length 3 and 4 into a single file.
     *
     * Purpose:
     * After the initial 2-byte split, some prefixes still produce large buckets
     * (for example in German common word starts like "sch"). To keep lookups fast without
     * creating large dense tables, this method writes additional refinement tables
     * only for prefixes whose bucket size exceeds `PREFIX34_THRESHOLD`.
     *
     * Each refinement table further subdivides an existing bucket by the next
     * character of the term.
     *
     * File structure:
     * The file consists of a sequence of independent blocks. Each block represents
     * one prefix and contains a 256-entry table that maps the next byte to a
     * `[low, high]` index range.
     *
     * Blocks come in two variants:
     *
     *   - Prefix length 3 (splitting by the 3rd byte)
     *     - identifies a 2-byte prefix (first two bytes of the term)
     *     - table entries describe ranges for terms starting with:
     *       (byte0, byte1, byte2)
     *
     *   - Prefix length 4 (splitting by the 4th byte)
     *     - identifies a 3-byte prefix (first three bytes of the term)
     *     - table entries describe ranges for terms starting with:
     *       (byte0, byte1, byte2, byte3)
     *
     * Only blocks for prefixes whose bucket size is greater than or equal to
     * `PREFIX34_THRESHOLD` are written. If no block exists for a prefix, lookup
     * simply continues with the current bounds and falls back to binary search.
     *
     * @param resource $out
     * @param array<int, array{0:int,1:int}> $prefix2Ranges
     * @param array<int, array<int, array{0:int,1:int}>> $prefix3Ranges
     * @param array<int, array<int, array{0:int,1:int}>> $prefix4Ranges
     */
    private function writePrefix34Buckets($out, array $prefix2Ranges, array $prefix3Ranges, array $prefix4Ranges): void
    {
        // Level 3 blocks for large prefix2 buckets
        foreach ($prefix2Ranges as $key2 => [$low2, $high2]) {
            if (($high2 - $low2 + 1) < self::PREFIX34_THRESHOLD) {
                continue;
            }

            fwrite($out, \chr(3) . pack('V', $key2));
            fwrite($out, $this->build256Table($prefix3Ranges[$key2] ?? []));
        }

        // Level 4 blocks only for large prefix3 buckets
        foreach ($prefix3Ranges as $key2 => $byByte2) {
            if (!isset($prefix2Ranges[$key2])) {
                continue;
            }
            [$low2, $high2] = $prefix2Ranges[$key2];
            if (($high2 - $low2 + 1) < self::PREFIX34_THRESHOLD) {
                continue;
            }

            foreach ($byByte2 as $byte2 => [$low3, $high3]) {
                if (($high3 - $low3 + 1) < self::PREFIX34_THRESHOLD) {
                    continue;
                }

                $key3 = ($key2 << 8) | $byte2;
                fwrite($out, \chr(4) . pack('V', $key3));
                fwrite($out, $this->build256Table($prefix4Ranges[$key3] ?? []));
            }
        }
    }
}
