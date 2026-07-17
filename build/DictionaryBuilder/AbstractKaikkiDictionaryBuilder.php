<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\DictionaryBuilder;

use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

abstract class AbstractKaikkiDictionaryBuilder extends AbstractFastSetDictionaryBuilder
{
    protected const EXTRA_TERMS = [];

    private const COMMON_FILTER_TAGS = [
        'form-of',
        'form_of',
    ];

    /**
     * @param array<string, mixed> $json
     */
    abstract protected function allowTermPostNormalize(string $term, array $json): bool;

    /**
     * @param array<string, mixed> $json
     */
    abstract protected function allowTermPreNormalize(string $term, array $json): bool;

    /**
     * @param array<string, mixed> $json
     *
     * @return list<string>
     */
    protected function collectTags(array $json): array
    {
        $allTags = [];
        $tags = $json['tags'] ?? [];
        if (\is_array($tags)) {
            foreach ($tags as $tag) {
                if (\is_string($tag)) {
                    $allTags[] = $tag;
                }
            }
        }

        $senses = $json['senses'] ?? [];
        if (!\is_array($senses)) {
            return array_values(array_map(strtolower(...), array_unique($allTags)));
        }

        foreach ($senses as $sense) {
            if (!\is_array($sense) || !\is_array($sense['tags'] ?? null)) {
                continue;
            }

            foreach ($sense['tags'] as $tag) {
                if (\is_string($tag)) {
                    $allTags[] = $tag;
                }
            }
        }

        return array_values(array_map(strtolower(...), array_unique($allTags)));
    }

    /**
     * @return array<string>
     */
    protected function doBuildTerms(SymfonyStyle $io): array
    {
        $rawDumpPath = __DIR__.'/../../var/kaikki_'.$this->getLocale()->toString().'.gz';
        $filesystem = new Filesystem();

        if (!$filesystem->exists($rawDumpPath)) {
            $io->info('Local raw dump file does not exist, will download now which can take a while.');
            $this->downloadRawDump($io, $rawDumpPath);
        }

        $gz = gzopen($rawDumpPath, 'rb');
        if (false === $gz) {
            throw new \RuntimeException(\sprintf('Unable to open raw dump: %s', $rawDumpPath));
        }

        $normalizer = $this->getNormalizer();
        $terms = [];
        $io->progressStart();

        while (false !== ($line = gzgets($gz))) {
            $term = $this->convertLineIntoTerm($line, $normalizer);

            if ($term) {
                $io->progressAdvance();
                $terms[] = $term;
            }
        }
        $io->progressFinish();

        gzclose($gz);

        foreach (static::EXTRA_TERMS as $additionalTerm) {
            $terms[] = $additionalTerm;
        }

        return $terms;
    }

    /**
     * Take the correct URLs from https://kaikki.org/dictionary/rawdata.html.
     */
    abstract protected function getDumpUrl(): string;

    abstract protected function getNormalizer(): NormalizerInterface;

    /**
     * @param array<string, mixed> $json
     * @param list<string>         $tags
     */
    protected function hasAllTags(array $json, array $tags): bool
    {
        return array_intersect($this->collectTags($json), $tags) === $tags;
    }

    /**
     * @param array<string, mixed> $json
     * @param list<string>         $tags
     */
    protected function hasAnyTag(array $json, array $tags): bool
    {
        return [] !== array_intersect($this->collectTags($json), $tags);
    }

    /**
     * @param array<string, mixed> $json
     */
    protected function hasCommonFilterTag(array $json): bool
    {
        return $this->hasAnyTag($json, self::COMMON_FILTER_TAGS);
    }

    /**
     * @param array<string, mixed> $json
     */
    protected function hasHypernym(array $json, string $hypernym): bool
    {
        $hypernyms = $json['hypernyms'] ?? [];

        foreach (\is_array($hypernyms) ? $hypernyms : [] as $hypernymEntry) {
            if (\is_array($hypernymEntry) && $hypernym === strtolower((string) ($hypernymEntry['word'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $json
     * @param list<string>         $allowedPos
     */
    protected function isAllowedPos(array $json, array $allowedPos): bool
    {
        $pos = strtolower((string) ($json['pos'] ?? ''));

        return \in_array($pos, $allowedPos, true);
    }

    /**
     * @param array<string, mixed> $json
     */
    protected function isClipped(array $json): bool
    {
        return $this->hasAllTags($json, ['abbreviation', 'clipping']);
    }

    /**
     * @param array<string, mixed> $json
     */
    protected function isSlang(array $json): bool
    {
        return $this->hasAllTags($json, ['abbreviation', 'slang']);
    }

    private function convertLineIntoTerm(string $line, NormalizerInterface $normalizer): string|null
    {
        $line = trim($line);

        if ('' === $line) {
            return null;
        }

        $json = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);

        if (!\is_array($json)) {
            return null;
        }

        // Ensure it's actually the correct locale
        if (($json['lang_code'] ?? '') !== $this->getLocale()->toString()) {
            return null;
        }

        $term = $json['word'] ?? null;
        if (!\is_string($term) || '' === $term) {
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
        $filesystem = new Filesystem();
        if (!$filesystem->exists($targetPath)) {
            $filesystem->dumpFile($targetPath, '');
        }

        $progress = $io->createProgressBar();
        $client = HttpClient::create();

        $response = $client->request(
            'GET',
            $this->getDumpUrl(),
            [
                'on_progress' => static function (int $dlNow, int $dlSize) use ($progress): void {
                    if ($dlSize > 0 && $progress->getMaxSteps() !== $dlSize) {
                        $progress->setMaxSteps($dlSize);
                    }
                    if ($dlNow > 0) {
                        $progress->setProgress($dlNow);
                    }
                },
            ],
        );

        $fp = fopen($targetPath, 'w');
        if (false === $fp) {
            throw new \RuntimeException(\sprintf('Unable to open target file: %s', $targetPath));
        }

        foreach ($client->stream($response) as $chunk) {
            if (false === fwrite($fp, $chunk->getContent())) {
                fclose($fp);

                throw new \RuntimeException(\sprintf('Unable to write target file: %s', $targetPath));
            }
        }

        fclose($fp);
    }
}
