<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Locale;

class WritableBinaryFileDictionary extends AbstractBinaryFileDictionary implements WritableDictionaryInterface
{
    public static function create(Locale $locale): self
    {
        return new self($locale);
    }

    public function write(string $pathToDirectory): void
    {
        $this->doWrite($pathToDirectory);
    }
}
