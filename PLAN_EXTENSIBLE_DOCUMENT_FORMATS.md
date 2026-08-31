# Making document formats extensible via a hook (instead of PR #76)

refs #37907, `@todo` remove once implemented.

## Why

PR [#76](https://github.com/eaudeweb/edw_modules/pull/76) (branch `37907-per-extension-grouping`, commit `235c6cf`) makes the
download-documents modal offer one checkbox per file extension (DOC, DOCX, XLS, XLSX, PPT, PPTX, RTF, TXT, DOCM) instead of the
grouped DOC/XLS/PPT options. That behaviour is right for the project that asked for it, but it is hardcoded into the shared
`edw_document` module, so **every** site that upgrades gets it whether it wants it or not, and the next project that wants a
different grouping has to patch the module again.

Goal: `edw_document` keeps shipping the current grouped defaults (no behaviour change on upgrade), and any project can redefine
the grouping — per-extension or otherwise — from its own module through an alter hook.

There is a second, structural reason to do this. A "format" is described today by **three parallel arrays that must be kept in
sync by hand**:

| what | where |
| --- | --- |
| extension → format id | `$extensionMapping` inside `DocumentManager::getUriType()` (`modules/edw_document/src/Services/DocumentManager.php:136-182`) |
| format id → label | `const ICONS_LABEL_INFO` (`DocumentManager.php:27-44`) |
| format id → icon path | `DocumentManager::documentIconsPathInfo()` (`DocumentManager.php:464-486`) |

That is exactly why PR #76 had to touch three places for one feature, and they have already drifted:
`documentIconsPathInfo()` has an `html` key that no extension ever maps to (`htm`/`shtml` map to `link`), and `getIcons()`
silently **drops any format missing from `documentIconsPathInfo()`** — add a format to `getUriType()` alone and its checkbox never
appears. Note also that the `images/icons` directory does not exist in this repo, so those icon paths are in practice only used as
a registry of valid format ids. Collapsing the three arrays into one registry is what makes a single clean alter hook possible.

## Approach

One registry, one hook.

```php
// Format id => definition.
'document' => [
  'label' => 'DOC',
  'icon' => "$iconsPath/x-office-document.png",
  'extensions' => ['csv', 'doc', 'docx', 'fodg', 'fodt', 'odf', 'odg', 'odt', 'pages', 'rtf'],
],
```

`getUriType()`, `getIcons()` and `documentIconsPathInfo()` all become views over this one array, and modules alter it with
`hook_edw_document_formats_alter()`.

**Start by reverting `235c6cf`** — the per-extension list moves out of `edw_document` and into the project module. Defaults in
`edw_document` stay exactly what 2.x has today.

## Step 1 — `DocumentManager` gets the module handler

In `modules/edw_document/src/Services/DocumentManager.php`, add a `ModuleHandlerInterface $moduleHandler` property, appended as
the **last** constructor argument so existing subclasses / manual instantiations don't fatal:

```php
public function __construct(..., Connection $database, ?ModuleHandlerInterface $moduleHandler = NULL) {
  ...
  $this->moduleHandler = $moduleHandler ?: \Drupal::service('module_handler');
}
```

Then append `'@module_handler'` to the `edw_document.document.manager` arguments in `modules/edw_document/edw_document.services.yml`.
`DownloadDocumentsForm` already injects `module_handler` the same way (`src/Form/DownloadDocumentsForm.php:80`) — follow that pattern.

## Step 2 — the registry

Add to `DocumentManager`, plus `protected $formats` and `protected $extensionMap` for static caching:

- `protected function getDefaultFormats(): array` — the current defaults, merged from the three arrays. Keep the exact same ids
  (`pdf`, `document`, `spreadsheet`, `presentation`, `video`, `text`, `image`, `link`) and the exact same extension lists that
  `getUriType()` has on 2.x, so nothing changes for existing sites. Fold the orphan `html` icon key into `link`. Build
  `$iconsPath` the way `documentIconsPathInfo()` does today.
- `public function getFormats(): array` — lazily build the defaults, run
  `$this->moduleHandler->alter('edw_document_formats', $formats)`, cache in `$this->formats`, return. Defensive-default each
  definition's `label` / `icon` / `extensions` after the alter and lowercase the extensions, so a sloppy hook can't produce
  notices downstream.
- `public function getExtensionMap(): array` — flip `getFormats()` into `extension => format id`, cached in `$this->extensionMap`.

## Step 3 — rewrite the three consumers as views over the registry

- **`getUriType()`** — same signature, same return; body becomes `pathinfo()` + `strtolower()` + lookup in `getExtensionMap()`.
  Delete the inline `$extensionMapping`.
- **`getIcons()`** — filter `getFormats()` by the ids in `$availableFormats` and return `id => label`. This drops the
  `array_search()`-on-icon-path logic PR #76 was already fixing, **and** the accidental dependency on `documentIconsPathInfo()`
  that made a format vanish when it had no icon. Keys stay format ids, so the form contract at
  `DownloadDocumentsForm.php:123-134` is unchanged.
- **`documentIconsPathInfo()`** — keep it (it's public API), but derive it: a map of `id => icon` from `getFormats()`. Add
  `@deprecated in 2.x, use getFormats() instead` to the docblock.
- **`const ICONS_LABEL_INFO`** — keep for BC, stop reading it internally, mark deprecated in a comment above it.
- Small fix while you're in there: `getOptions()` (`DocumentManager.php:312-329`) pushes `NULL` into `$availableFormats` for any
  extension that belongs to no format — wrap the result in `array_filter()`.

## Step 4 — the helper

A static method on `DocumentManager`, so a project's hook is three lines instead of twenty lines of array juggling that every
project would otherwise copy-paste:

```php
/**
 * Splits extensions out of their grouped formats into per-extension formats.
 *
 * @param array $formats
 *   The formats registry, as passed to hook_edw_document_formats_alter().
 * @param array $extensions
 *   Extensions to promote to formats of their own.
 * @param array $labels
 *   Optional extension => label overrides; defaults to the uppercased extension.
 */
public static function splitFormatsByExtension(array &$formats, array $extensions, array $labels = []) {
  foreach ($extensions as $extension) {
    $extension = strtolower($extension);
    foreach ($formats as $id => $format) {
      if (!in_array($extension, $format['extensions'] ?? [], TRUE)) {
        continue;
      }
      // Inherit the icon of the group the extension is leaving.
      $formats[$extension] = [
        'label' => $labels[$extension] ?? strtoupper($extension),
        'icon' => $format['icon'] ?? '',
        'extensions' => [$extension],
      ];
      $formats[$id]['extensions'] = array_values(array_diff($format['extensions'], [$extension]));
      // A group with nothing left in it must go, or it shows an empty checkbox.
      if (empty($formats[$id]['extensions'])) {
        unset($formats[$id]);
      }
      break;
    }
  }
}
```

Two edge cases the implementation must respect, and that are worth a manual check: an extension that is **already** its own
format (`pdf`) must come out unchanged rather than deleting itself, and an unknown extension must be a no-op, not a new empty
format.

## Step 5 — document the hook

Add to `modules/edw_document/edw_document.api.php`, next to the existing `hook_field_files_alter()` /
`hook_field_external_links_alter()`:

```php
/**
 * Alters the document formats used by the download modal.
 *
 * Each format is a group of file extensions shown as one checkbox in the
 * download-documents modal, keyed by format id:
 * @code
 * 'document' => [
 *   'label' => 'DOC',
 *   'icon' => '/modules/custom/edw_modules/.../x-office-document.png',
 *   'extensions' => ['csv', 'doc', 'docx', 'odt', ...],
 * ]
 * @endcode
 *
 * @param array $formats
 *   The format definitions, keyed by format id.
 *
 * @see \Drupal\edw_document\Services\DocumentManager::getFormats()
 */
function hook_edw_document_formats_alter(array &$formats) {
  // One checkbox per office extension instead of grouped DOC/XLS/PPT.
  \Drupal\edw_document\Services\DocumentManager::splitFormatsByExtension($formats, [
    'doc', 'docx', 'docm', 'rtf', 'txt', 'xls', 'xlsx', 'ppt', 'pptx',
  ]);
}
```

On naming: the two existing hooks in this module are un-namespaced (`hook_field_files_alter`), which is a Drupal anti-pattern —
prefix the new one with the module name and leave the old ones alone.

Also add a short **Document formats** section to `modules/edw_document/README.md` with the same example, since that's where site
builders look.

## Step 6 — the project side (what actually delivers #37907)

In the project that needs per-extension grouping, in its own custom module (e.g. `mysite_document.module`):

```php
use Drupal\edw_document\Services\DocumentManager;

/**
 * Implements hook_edw_document_formats_alter().
 */
function mysite_document_edw_document_formats_alter(array &$formats) {
  DocumentManager::splitFormatsByExtension($formats, [
    'doc', 'docx', 'docm', 'rtf', 'txt', 'xls', 'xlsx', 'ppt', 'pptx',
  ]);
}
```

That is the whole of PR #76, as project code. Nothing in `edw_document` needs to know that project exists.

## Files to touch

| file | change |
| --- | --- |
| `modules/edw_document/src/Services/DocumentManager.php` | revert `235c6cf`; add module handler, registry, helper; rewrite the three consumers |
| `modules/edw_document/edw_document.services.yml` | append `'@module_handler'` |
| `modules/edw_document/edw_document.api.php` | document `hook_edw_document_formats_alter()` |
| `modules/edw_document/README.md` | "Document formats" section |
| project module (outside this repo) | the alter hook above |

No update hook and no data migration: format ids are computed at runtime from file extensions and never stored, so changing the
grouping only changes what the modal renders.

## Verification

There is no test infrastructure in this repo, so verify on a site:

1. `drush cr`, open a documents view, select a few nodes/media with mixed attachments, open the download modal. **Without any
   project hook**, the checkboxes must be identical to 2.x today — DOC/XLS/PPT/PDF groups, not per-extension. This is the
   regression that matters most.
2. Download with one format ticked, then with several; confirm the ZIP contains exactly the matching files — `getFilteredFiles()`
   filters through `getUriType()`, so it exercises the same registry.
3. Add the project hook from step 6, `drush cr`, reopen the modal: one checkbox per extension, labels DOC/DOCX/XLS/…, and each one
   downloads only files of that extension.
4. Attach a file with an extension in no format at all (e.g. `.zip`) and confirm the modal shows no empty/unlabelled checkbox
   (that's the `array_filter()` in `getOptions()`).
5. Single-format case: when only one format is available the form auto-selects it (`DownloadDocumentsForm.php:132`) — confirm that
   still works.
6. `phpcs --standard=Drupal,DrupalPractice` on the changed files (PR #76 left missing trailing commas and a `'rtf'=>` spacing
   issue — don't carry those over).

## Ordering

PR #76 should be closed, or force-pushed to this approach, rather than merged first — merging it and refactoring afterwards means
shipping a behaviour change to every site in between, then taking it back.
