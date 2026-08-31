<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Benchmark;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PhpBench\Attributes\BeforeClassMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Symfony\Component\Filesystem\Filesystem;

#[BeforeClassMethods('ensureMoviesJson')]
#[BeforeMethods('setUp')]
#[Revs(1)]
#[Iterations(3)]
#[Warmup(0)]
#[OutputTimeUnit('seconds', precision: 3)]
#[Groups(['movies'])]
class MovieTokenizerBench
{
    private const MOVIES_SHA256 = '465ffb283cb9fdf6146b4cafcc437ac124278a507b71cb35c5ccf35198a57d76';

    private const MOVIES_URL = 'https://raw.githubusercontent.com/meilisearch/documentation/652031043410d0193b23b5647956b4bdaf44d287/assets/datasets/movies.json';

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $movies;

    private int $termCount = 0;

    private Tokenizer $tokenizer;

    public function setUp(): void
    {
        $json = file_get_contents(self::moviesJsonPath());
        if (false === $json) {
            throw new \RuntimeException('Failed to read the movie benchmark corpus.');
        }

        $this->movies = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->termCount = 0;
        $this->tokenizer = Tokenizer::createFromPreconfiguredLocaleConfiguration(Locale::fromString('en'));
    }

    public function benchTokenizeMovies(): void
    {
        foreach ($this->movies as $movie) {
            $this->tokenizeAttribute((string) $movie['title']);
            $this->tokenizeAttribute((string) $movie['overview']);
        }
    }

    public static function ensureMoviesJson(): void
    {
        $path = self::moviesJsonPath();
        if (is_file($path) && self::MOVIES_SHA256 === hash_file('sha256', $path)) {
            return;
        }

        $movies = file_get_contents(self::MOVIES_URL);
        if (false === $movies || '' === $movies) {
            throw new \RuntimeException('Failed to download the movie benchmark corpus.');
        }
        if (self::MOVIES_SHA256 !== hash('sha256', $movies)) {
            throw new \RuntimeException('The movie benchmark corpus checksum does not match.');
        }

        $filesystem = new Filesystem();
        $filesystem->mkdir(\dirname($path));
        $filesystem->dumpFile($path, $movies);
    }

    private static function moviesJsonPath(): string
    {
        return \dirname(__DIR__, 2).'/var/movies.json';
    }

    private function tokenizeAttribute(string $text): void
    {
        $this->termCount += \count($this->tokenizer->tokenize($text)->allTermsWithVariants());
    }
}
