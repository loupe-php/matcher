<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Locale;

use Loupe\Matcher\Build\DictionaryBuilderInterface;
use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\WritableBinaryFileDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\WritableDictionaryInterface;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

abstract class AbstractKaikkiDictionary implements DictionaryBuilderInterface
{
    public function buildDirectory(SymfonyStyle $io): WritableDictionaryInterface
    {
        $rawDumpPath = __DIR__ . '/../../var/kaikki_' . $this->getLocale()->toString() . '.gz';

        if (!file_exists($rawDumpPath)) {
            $io->info('Local raw dump file does not exist, will download now which can take a while.');
            $this->downloadRawDump($io, $rawDumpPath);
        }

        $gz = gzopen($rawDumpPath, 'rb');

        $normalizer = new Normalizer();
        $dictionary = WritableBinaryFileDictionary::create($this->getLocale());

        $io->progressStart();
        while (!gzeof($gz)) {
            $term = $this->convertLineIntoTerm(gzgets($gz));

            if ($term) {
                $io->progressAdvance();
                $dictionary->add($normalizer->normalize($term));
            }
        }
        $io->progressFinish();

        gzclose($gz);

        return $dictionary;
    }

    abstract protected function allowTerm(string $term, array $json): bool;

    /**
     * Take the correct URLs from https://kaikki.org/dictionary/rawdata.html.
     */
    abstract protected function getDumpUrl(): string;

    protected function hasTag(array $json, string $tag): bool
    {
        $topTags = $json['tags'] ?? [];
        $topTagsLower = array_map('strtolower', $topTags);
        if (\in_array($tag, $topTagsLower, true)) {
            return true;
        }

        $senses = $entry['senses'] ?? [];

        foreach ($senses as $sense) {
            $tags = $sense['tags'] ?? [];
            $tagsLower = array_map('strtolower', $tags);

            if (\in_array($tag, $tagsLower, true)) {
                return true;
            }
        }

        return false;
    }

    protected function isAllowedPos(array $json, array $allowedPos): bool
    {
        $pos = strtolower((string) ($json['pos'] ?? ''));

        return \in_array($pos, $allowedPos, true);
    }

    private function convertLineIntoTerm(string $line): ?string
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        $json = json_decode($line, true, JSON_THROW_ON_ERROR);

        if (!\is_array($json)) {
            return null;
        }

        // Ensure it's actually the correct locale
        if (($json['lang_code'] ?? '') !== $this->getLocale()->toString()) {
            return null;
        }

        $term = $json['word'] ?? null;
        if (!\is_string($term) || $term === '') {
            return null;
        }

        if (!$this->allowTerm($term, $json)) {
            return null;
        }

        return $term;
    }

    private function downloadRawDump(SymfonyStyle $io, string $targetPath): void
    {
        if (!file_exists($targetPath)) {
            (new Filesystem())->dumpFile($targetPath, '');
        }

        $progress = $io->createProgressBar();
        $client = HttpClient::create();

        $response = $client->request('GET', $this->getDumpUrl(), [
            'on_progress' => function (int $dlNow, int $dlSize) use ($progress) {
                if ($dlSize > 0 && $progress->getMaxSteps() !== $dlSize) {
                    $progress->setMaxSteps($dlSize);
                }
                if ($dlNow > 0) {
                    $progress->setProgress($dlNow);
                }
            },
        ]);

        $fp = fopen($targetPath, 'wb');

        foreach ($client->stream($response) as $chunk) {
            fwrite($fp, $chunk->getContent());
        }

        fclose($fp);
    }
}
