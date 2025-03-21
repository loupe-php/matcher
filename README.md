## Context cropper

A simple PHP library to crop highlighted context coming from e.g. a search engine.

Usage:

```php
$context = 'Lorem ipsum dolor sit amet, <mark>consectetur</mark> adipiscing elit. Etiam eleifend, augue in dictum lacinia, nisi lacus mollis <mark>massa</mark>, a pulvinar felis dui nec nisl. Pellentesque justo erat, sollicitudin ac dolor finibus, dapibus lacinia diam.';

$cropper = new \Loupe\ContextCropper\ContextCropper(
    20, // Context length in characters
    '[…]',
    '<mark>',
    '</mark>'
);

echo $cropper->apply($context); // […]ipsum dolor sit amet, <mark>consectetur</mark> adipiscing elit. Etiam[…]lacinia, nisi lacus mollis <mark>massa</mark>, a pulvinar felis dui[…]
```
