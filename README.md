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


### Changes since v2.5

#### edw_paragraphs_gallery
If you update from version 2.5 or below and the website is using paragraph
`edw_paragraphs_gallery` then you will need to replace path `@edw_paragraphs_gallery/components/edw-gallery.twig`
with `@edw_paragraphs_gallery/components/gallery/gallery.twig`.