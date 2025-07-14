# Loupe Matcher

A PHP library for search term highlighting and text snippet generation. Transform search results into user-friendly formatted text with highlighted matches and contextual cropping.

> <mark>Lorem ipsum</mark> dolor sit amet, consetetur [...] no sea takimata sanctus est <mark>lorem</mark> est <mark>ipsum</mark> dolor sit amet. <mark>Lorem ipsum</mark> dolor sit amet, consetetur [...] dolore te feugait nulla facilisi <mark>lorem ipsum</mark> dolor sit amet, consectetuer [...]

> [!CAUTION]
> Work in progress. Expect frequent changes to API and functionality.

## Installation

```bash
composer require loupe/matcher
```

## Quick Start

Here's a simple example of how to use Loupe Matcher to highlight search terms in a text document and crop around the highlights:

```php
use Loupe\Matcher\Tokenizer\Tokenizer;
use Loupe\Matcher\Matcher;
use Loupe\Matcher\Formatter;
use Loupe\Matcher\FormatterOptions;

$tokenizer = new Tokenizer();
$matcher = new Matcher($tokenizer);
$formatter = new Formatter($matcher);

$options = (new FormatterOptions())
    ->withEnableHighlight()
    ->withEnableCrop()
    ->withCropLength(10);

$result = $formatter->format(
    'This is a long document with many words to search through and compare.',
    'search words',
    $options
);

// "...with many <em>words</em> to <em>search</em> through..."
echo $result->getFormattedText();
```

## Core Components

### Tokenizer

**Purpose:** Breaks text into searchable tokens (words, phrases, terms) for accurate matching.

The `Tokenizer` converts strings into `TokenCollection` objects, handling:

- Word boundaries using `ext-intl` rules
- Phrase groups (quoted terms like `"exact phrase"`)
- Negated terms (prefixed with `-`)
- Locale-specific tokenization

```php
$tokenizer = new Tokenizer('en_US'); // Optional locale
$tokens = $tokenizer->tokenize('search for "exact phrase" -exclude');

$tokens->all();          // All tokens
$tokens->phraseGroups(); // Quoted phrases only
$tokens->allNegated();   // Terms to exclude
```

### Matcher

**Purpose:** Finds which tokens in your text match the search query.

The `Matcher` compares tokenized text against search terms, with support for:

- Stop word filtering (ignore common words like "the", "and")
- Match span calculation (start/end positions)
- Flexible matching between token collections

```php
$matcher = new Matcher($tokenizer, ['the', 'and', 'or']); // Stop words
$matches = $matcher->calculateMatches('Text to search', 'search query');

// Get position information for highlighting
$spans = $matcher->calculateMatchSpans('Text to search', 'query', $matches);
foreach ($spans as $span) {
    echo "Match at position {$span->getStartPosition()}-{$span->getEndPosition()}";
}
```

### Formatter

**Purpose:** Combines matching and highlighting to create formatted output with context.

The `Formatter` orchestrates the entire process:

- Highlights matched terms with HTML tags
- Crops text to show relevant context around matches
- Configurable through `FormatterOptions`

```php
$formatter = new Formatter($matcher);

$options = (new FormatterOptions())
    ->withEnableHighlight()
    ->withHighlightStartTag('<mark>')
    ->withHighlightEndTag('</mark>')
    ->withEnableCrop()
    ->withCropLength(150)
    ->withCropMarker('...');

$result = $formatter->format($text, $query, $options);
echo $result->getFormattedText();
```

## Advanced Usage

### Custom Tokenizer

Implement `TokenizerInterface` for specialized tokenization:

```php
class CustomTokenizer implements TokenizerInterface {
    public function tokenize(string $text): TokenCollection {
        // Your custom tokenization logic
    }

    public function matches(Token $token, TokenCollection $tokens): bool {
        // Your custom logic for checking if a token is a match
    }
}
```

### Pre-highlighted Text Cropping

When you already have highlighted text that needs cropping:

```php
$cropper = new \Loupe\Matcher\Formatting\Cropper(
    cropLength: 50,
    cropMarker: '…',
    highlightStartTag: '<em>',
    highlightEndTag: '</em>'
);

// "...text with <em>highlighted</em> terms."
echo $cropper->cropHighlightedText('Long text with <em>highlighted</em> terms.');
```

### Using Pre-calculated Matches

When you already have a `TokenCollection` of matches (e.g., from a previous search operation or external source), you can format text directly without re-calculating matches. This approach is useful when your search engine already provides match information or you want to cache match results for performance.

```php
// Assume you already have matches from somewhere else
$existingMatches = new TokenCollection(/* ... */);

// Set up the tokenizer, matcher, and formatter as usual
$tokenizer = new Tokenizer();
$matcher = new Matcher($tokenizer);
$formatter = new Formatter($matcher);
$options = (new FormatterOptions())
    ->withEnableHighlight()
    ->withEnableCrop()
    ->withCropLength(100);

// Format using the existing matches - no duplicate processing
$result = $formatter->format($text, $query, $options, matches: $existingMatches);
echo $result->getFormattedText();
```
