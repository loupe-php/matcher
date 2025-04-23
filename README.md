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

### Highlight matches

```php
$highlighter = new \Loupe\Matcher\Formatting\Highlighter(
    'lorem ipsum',
    '<mark>',
    '</mark>'
);

$context = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam eleifend, augue in dictum lacinia, nisi lacus mollis massa, a pulvinar felis dui nec nisl. Pellentesque justo erat, sollicitudin ac dolor finibus, dapibus lacinia diam.';

echo $matcher->apply($context); // <mark>Lorem ipsum</mark> dolor sit amet, consectetur adipiscing elit. Etiam eleifend, augue in dictum lacinia, nisi lacus mollis massa, a pulvinar felis dui nec nisl. Pellentesque justo erat, sollicitudin ac dolor finibus, dapibus lacinia diam.
```

### Crop text around matches

```php
$cropper = new \Loupe\Matcher\ContextCropper(
    20, // Context length in characters
    '[…]',
    '<mark>',
    '</mark>'
);

$context = 'Lorem ipsum dolor sit amet, <mark>consectetur</mark> adipiscing elit. Etiam eleifend, augue in dictum lacinia, nisi lacus mollis <mark>massa</mark>, a pulvinar felis dui nec nisl. Pellentesque justo erat, sollicitudin ac dolor finibus, dapibus lacinia diam.';

echo $cropper->apply($context); // […]ipsum dolor sit amet, <mark>consectetur</mark> adipiscing elit. Etiam[…]lacinia, nisi lacus mollis <mark>massa</mark>, a pulvinar felis dui[…]
```
