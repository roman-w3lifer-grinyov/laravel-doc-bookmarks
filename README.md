# laravel-doc-bookmarks

- [Installation](#installation)
- [Usage](#usage)

![Example](screenshot.png)

## Installation

``` sh
composer require w3lifer/laravel-doc-bookmarks
```

## Usage

- Create `laravel-doc-bookmarks.php` file with the following content:

``` php
<?php

use w3lifer\laravel\DocBookmarks;

$docBookmarks = new DocBookmarks([
    'version' => '5.7', // "master" by default
]);

file_put_contents(
    __DIR__ . '/laravel-doc-bookmarks-as-array.php',
    '<?php' . "\n\n" .
        var_export($docBookmarks->getAsArray(), true) . ';'
);

file_put_contents(
    __DIR__ . '/laravel-doc-bookmarks-as-netscape-bookmarks.html',
    $docBookmarks->getAsNetscapeBookmarks()
);

echo 'Done!' . "\n";
```

- Run it from the command line to get two files with bookmarks:

``` sh
php laravel-doc-bookmarks.php
```

---

- Run `vendor/bin/laravel-doc-bookmarks` to get `laravel-doc-bookmarks-as-array.php` and `laravel-doc-bookmarks-as-netscape-bookmarks.html` in the parent directory of the `vendor` directory.
