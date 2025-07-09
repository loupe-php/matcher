## Loupe Matcher

> [!CAUTION]
> Work in progress

A utility to identify and highlight search terms and create snippets around matched sections.

> <mark>Lorem ipsum</mark> dolor sit amet, consetetur [...] no sea takimata sanctus est <mark>lorem</mark> est <mark>ipsum</mark> dolor sit amet. <mark>Lorem ipsum</mark> dolor sit amet, consetetur [...] dolore te feugait nulla facilisi <mark>lorem ipsum</mark> dolor sit amet, consectetuer [...]

## Installation

```bash
composer require loupe/matcher
```

## Usage

### Tokenizer

In order to work with string matching for both, highlighting and cropping, we need to tokenize a string into terms, 
phrases etc. This library ships with a basic tokenizer built on top of the `ext-intl` rules but you can implement 
your own tokenizer by implementing the `TokenizerInterface`. Its goal is to take a string and convert it into a 
`TokenCollection`. It is also responsible to decide, whether a given `Token` instance matches any of the tokens in a 
given `TokenCollection`:

```php
$tokenizer = new \Loupe\Matcher\Tokenizer\Tokenizer(); // optionally takes a locale to improve tokenization
$tokenCollection = $tokenizer->tokenize('this is my string');

// Now you can use all sorts of helper functions
$tokenCollection->all(); // all Token instances
$tokenCollection->allNegated(); // all negated terms (- prefixed)
$tokenCollection->phraseGroups(); // tokens within phrase groups (inside quotation marks, e.g. "this is a phrase")
// etc.
```

### Matcher

The `Matcher` helper is here to help you find matches between two `TokenCollection`s (or strings for simplicity). It requires a `Tokenizer` and optionally accepts stop words:

```php
$tokenizer = new \Loupe\Matcher\Tokenizer\Tokenizer();
$matcher = new \Loupe\Matcher\Matcher($tokenizer, ['a', 'and', 'the']);
$matchingTokenCollection = $matcher->calculateMatches('The text to query', 'query');
```

`$matchingTokenCollection` will now contain all Token instances that match the query.

Sometimes you might be interested in the spans of the matches (the start and end positions of the tokens matched). The spans will include any matching words as well as stop words around or in between the matching tokens.

```php
$spans = $matcher->calculateMatchSpans('This is my original text which I want to query.', 'query', $matchingTokenCollection);
foreach ($spans as $span) {
    echo 'This span started at:' . $span->getStartPosition();
    echo 'This span ended at:' . $span->getEndPosition();
    echo 'This span has a length of:' . $span->getLength();
}
```

### Formatter

The `Formatter` takes a `FormatterOptions` instance and formats directly on two strings (text and query) according to your
configuration. You can also pass a `TokenCollection` for the `$query` directly if you want and have tokenized those
before. The `$text`, however, has to be a string.

```php
$tokenizer = new \Loupe\Matcher\Tokenizer\Tokenizer();
$matcher = new \Loupe\Matcher\Matcher($tokenizer);

$formatter = new \Loupe\Matcher\Formatter($matcher);

$options = (new \Loupe\Matcher\FormatterOptions())
    ->withEnableHighlight() // enable highlighting
    ->withHighlightStartTag('<b>') // default: <em>
    ->withHighlightEndTag('</b>') // default: </em>
    ->withEnableCrop() // enable cropping
    ->withCropLength(40) // default: 50
    ->withCropMarker('.......') // default: …
;

$result = $formatter->format('This is my original text which I want to query.', 'query', $options);

echo 'This is the formatted result: ' . $result->getFormattedText();
```

### Cropping pre-highlighted results

Sometimes, you have a pre-highlighted text that needs cropping (e.g. because your search engine supports highlighting
but not context cropping), you can use the `Cropper` formatter directly in this case:

```php
$cropper = new \Loupe\Matcher\Formatting\Cropper(
    $cropLength = 10,
    $cropMarker = '…',
    $highlightStartTag = '<em>',
    $highlightEndTag = '</em>',
);

echo $cropper->cropHighlightedText('This is a <em>test</em> string.'); // Outputs: …a <em>test</em> st…
```
