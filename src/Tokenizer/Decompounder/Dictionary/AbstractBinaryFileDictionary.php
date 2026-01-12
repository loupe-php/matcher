<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Locale;

abstract class AbstractBinaryFileDictionary implements DictionaryInterface
{
    private const MAX_LENGTH_IN_BYTES = 256;

    private const U32_NONE = 0xFFFFFFFF;

    private string $blob = '';

    private int $count = 0;

    private string $index = '';

    private string $prefixBuckets = '';

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

        // Use the first two bytes of the term to search in our prefix index for matching
        // lower and upper bounds. This reduces lookup time drastically because we now have
        // split up our entire index into a maximum of 65,536 buckets, and we thus only need to work within
        // the matching bucket.
        $byte0 = isset($term[0]) ? \ord($term[0]) : 0;
        $byte1 = isset($term[1]) ? \ord($term[1]) : 0;
        $key = ($byte0 << 8) | $byte1;

        $bucket = unpack('Vlow/Vhigh', substr($this->prefixBuckets, $key * 8, 8));
        $lowerIndex = $bucket['low'] ?? self::U32_NONE;

        // If no term exists for that prefix2, we can return immediately
        if ($lowerIndex === self::U32_NONE) {
            return false;
        }

        $upperIndex = $bucket['high'] ?? -1;

        // Standard binary search here
        while ($lowerIndex <= $upperIndex) {
            // Choose the middle index between current bounds
            $middleIndex = ($lowerIndex + $upperIndex) >> 1;

            // Read the term stored at that index position
            $currentTerm = $this->termAt($middleIndex);

            // Compare the requested term with the term from the index
            $comparisonResult = strcmp($term, $currentTerm);

            // Match found – the term exists in the dictionary
            if ($comparisonResult === 0) {
                return true;
            }

            // Standard binary search step:
            // If searchTerm < currentTerm, continue searching in lower half
            if ($comparisonResult < 0) {
                $upperIndex = $middleIndex - 1;
            }
            // Otherwise search in upper half
            else {
                $lowerIndex = $middleIndex + 1;
            }
        }

        return false;
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
        $termsPath = $directory . '/terms';
        $indexPath = $directory . '/index';
        $prefixPath = $directory . '/prefix_buckets';
        $blob = null;
        $index = null;
        $prefixBuckets = null;

        $load = function (bool $decode = true) use (&$load, $directory, $termsPath, $indexPath, $prefixPath, &$blob, &$index, &$prefixBuckets) {
            $blob = @file_get_contents($termsPath);
            $index = @file_get_contents($indexPath);
            $prefixBuckets = @file_get_contents($prefixPath);

            if ($decode && ($blob === false || $index === false || $prefixBuckets === false)) {
                $this->decodeDictionary($directory);
                $load(false);
            }
        };
        $load();

        if (!\is_string($blob) || !\is_string($index) || !\is_string($prefixBuckets)) {
            throw new \RuntimeException('Cannot load blob from directory.');
        }

        $this->blob = $blob;
        $this->index = $index;
        $this->prefixBuckets = $prefixBuckets;
        $this->count = intdiv(\strlen($index), 4);
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

        $termsOut = fopen($directory . '/terms', 'wb');
        if ($termsOut === false) {
            throw new \RuntimeException('Cannot open terms for writing');
        }

        $indexOut = fopen($directory . '/index', 'wb');
        if ($indexOut === false) {
            throw new \RuntimeException('Cannot open index for writing');
        }

        $prefixBucketsOut = fopen($directory . '/prefix_buckets', 'wb');
        if ($prefixBucketsOut === false) {
            throw new \RuntimeException('Cannot open prefix_buckets for writing');
        }

        $previous = '';
        $position = 0;
        $prefixRanges = [];
        $termIndex = 0;

        while (!gzeof($directoryHandle)) {
            $header = $this->gzReadBytesExactly($directoryHandle, 2);
            if ($header === null) {
                break;
            }

            $prefixLen = \ord($header[0]);
            $suffixLen = \ord($header[1]);

            $suffix = $this->gzReadBytesExactly($directoryHandle, $suffixLen);
            if ($suffix === null) {
                throw new \RuntimeException(\sprintf('Gzipped file is incorrect. Expected %d bytes but got none.', $suffixLen));
            }

            $term = substr($previous, 0, $prefixLen) . $suffix;

            // Our index file contains all positions as unsigned 32-bit integer (should be easily enough)
            fwrite($indexOut, pack('V', $position));
            fwrite($termsOut, $term . "\n");

            $byte0 = isset($term[0]) ? \ord($term[0]) : 0;
            $byte1 = isset($term[1]) ? \ord($term[1]) : 0;
            $key = ($byte0 << 8) | $byte1;

            // If we don't have this prefix yet, set lower and higher position the to the same value
            if (!isset($prefixRanges[$key])) {
                $prefixRanges[$key] = [$termIndex, $termIndex];
            } else {
                // Otherwise, update the higher value only (the range increases)
                $prefixRanges[$key][1] = $termIndex;
            }
            $termIndex++;

            $position += \strlen($term) + 1;
            $previous = $term;
        }

        // Write our prefix buckets index that contains the low and high index positions for all prefixes (the first 2 bytes)
        // for faster lookups
        for ($k = 0; $k < 65536; $k++) {
            if (!isset($prefixRanges[$k])) {
                fwrite($prefixBucketsOut, pack('V', self::U32_NONE) . pack('V', self::U32_NONE));
            } else {
                [$low, $high] = $prefixRanges[$k];
                fwrite($prefixBucketsOut, pack('V', $low) . pack('V', $high));
            }
        }
        fclose($prefixBucketsOut);
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
        return unpack('Voffset', substr($this->index, $index * 4, 4))['offset'] ?? 0;
    }

    private function termAt(int $index): string
    {
        $start = $this->offsetAt($index);
        $end = ($index + 1 < $this->count) ? $this->offsetAt($index + 1) : \strlen($this->blob);

        return rtrim(substr($this->blob, $start, $end - $start), "\n");
    }
}
