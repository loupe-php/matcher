<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\DictionaryBuilder;

use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

abstract class AbstractKaikkiDictionaryBuilder extends AbstractFastSetDictionaryBuilder
{
    private const COMMON_FILTER_TAGS = [
        'form-of',
        'form_of',
    ];

    abstract protected function allowTermPostNormalize(string $term, array $json): bool;

    abstract protected function allowTermPreNormalize(string $term, array $json): bool;

    protected function collectTags(array $json): array
    {
        $allTags = $json['tags'] ?? [];
        $senses = $json['senses'] ?? [];

        foreach ($senses as $sense) {
            $tags = $sense['tags'] ?? [];
            foreach ($tags as $tag) {
                $allTags[] = $tag;
            }
        }

        return array_map('strtolower', array_unique($allTags));
    }

    /**
     * @return array<string>
     */
    protected function doBuildTerms(SymfonyStyle $io): array
    {
        $rawDumpPath = __DIR__ . '/../../var/kaikki_' . $this->getLocale()->toString() . '.gz';

        if (!file_exists($rawDumpPath)) {
            $io->info('Local raw dump file does not exist, will download now which can take a while.');
            $this->downloadRawDump($io, $rawDumpPath);
        }

        $gz = gzopen($rawDumpPath, 'rb');

        $normalizer = $this->getNormalizer();
        $terms = [];
        $io->progressStart();

        while (!gzeof($gz)) {
            $term = $this->convertLineIntoTerm(gzgets($gz), $normalizer);

            if ($term) {
                $io->progressAdvance();
                $terms[] = $term;
            }
        }
        $io->progressFinish();

        gzclose($gz);

        return $terms;
    }

    /**
     * Take the correct URLs from https://kaikki.org/dictionary/rawdata.html.
     */
    abstract protected function getDumpUrl(): string;

    abstract protected function getNormalizer(): NormalizerInterface;

    protected function hasAllTags(array $json, array $tags): bool
    {
        return array_intersect($this->collectTags($json), $tags) === $tags;
    }

    protected function hasAnyTag(array $json, array $tags): bool
    {
        return array_intersect($this->collectTags($json), $tags) !== [];
    }

    protected function hasCommonFilterTag(array $json): bool
    {
        return $this->hasAnyTag($json, self::COMMON_FILTER_TAGS);
    }

    protected function hasHypernym(array $json, string $hypernym): bool
    {
        $hypernyms = $json['hypernyms'] ?? [];
        foreach ($hypernyms as $hypernymEntry) {
            if ($hypernym === strtolower((string) $hypernymEntry['word'] ?? '')) {
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

    protected function isClipped(array $json): bool
    {
        return $this->hasAllTags($json, ['abbreviation', 'clipping']);
    }

    protected function isSlang(array $json): bool
    {
        return $this->hasAllTags($json, ['abbreviation', 'slang']);
    }

    private function convertLineIntoTerm(string $line, NormalizerInterface $normalizer): ?string
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

        if (!$this->allowTermPreNormalize($term, $json)) {
            return null;
        }

        $term = $normalizer->normalize($term);

        if (!$this->allowTermPostNormalize($term, $json)) {
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
