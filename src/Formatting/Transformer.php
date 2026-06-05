<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

interface Transformer
{
    public function transform(FormattedText $input): FormattedText;
}
