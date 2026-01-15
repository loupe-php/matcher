<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Locale;
use Toflar\FastSet\FastSet;

abstract class AbstractFastSetDictionary implements DictionaryInterface
{
    private const FILE_NAME_TERMS = 'terms';

    private ?FastSet $fastSet = null;

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
        if ($this->fastSet !== null) {
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
        if ($this->fastSet === null) {
            throw new \LogicException('This dictionary is not initalized yet.');
        }

        return $this->fastSet->has($term);
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
        $fastSet = new FastSet($directory);

        try {
            $fastSet->initialize();
        } catch (\Throwable) {
            $this->decodeDictionary($directory);
            $fastSet->build($directory . '/' . self::FILE_NAME_TERMS);
            $fastSet->initialize();
        }

        $this->fastSet = $fastSet;
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
        $previous = '';

        while (!gzeof($directoryHandle)) {
            $term = $this->readNextTermFromGz($directoryHandle, $previous);
            if ($term === null) {
                break;
            }

            fwrite($termsOut, $term . "\n");
            $previous = $term;
        }

        fclose($termsOut);
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
}
