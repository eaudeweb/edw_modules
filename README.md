# EDW Modules

A collection of custom modules

## Installation

Add
```
{
    "type": "git",
    "url": "https://github.com/eaudeweb/edw_modules.git"
}
```

Run
```composer require eaudeweb/edw_modules:^2.0```

## Running tests

Drupal tests need a full Drupal codebase to bootstrap, so they are run from a
site that has this module installed. Core's test bootstrap auto-registers the
`Drupal\Tests\<module>\` namespaces, so no extra autoloader configuration is
needed — but the site must have `drupal/core-dev` (it provides PHPUnit):

```
ddev composer require --dev drupal/core-dev
```

Every test class in this repository is tagged with the `@group` of the module it
covers, so a whole module's suite runs as one selector:

```
ddev exec vendor/bin/phpunit -c web/core --group edw_document
```

Unit and Kernel tests need nothing else. Functional and FunctionalJavascript
tests additionally need `SIMPLETEST_BASE_URL`, `SIMPLETEST_DB` and
`BROWSERTEST_OUTPUT_DIRECTORY` set in the site's `phpunit.xml`, and
FunctionalJavascript needs a running WebDriver.

To run a single layer:

```
ddev exec vendor/bin/phpunit -c web/core --group edw_document --testsuite unit
ddev exec vendor/bin/phpunit -c web/core --group edw_document --testsuite kernel
ddev exec vendor/bin/phpunit -c web/core --group edw_document --testsuite functional
```


### Changes since v2.5

#### edw_paragraphs_gallery
If you update from version 2.5 or below and the website is using paragraph
`edw_paragraphs_gallery` then you will need to replace path `@edw_paragraphs_gallery/components/edw-gallery.twig`
with `@edw_paragraphs_gallery/components/gallery/gallery.twig`.