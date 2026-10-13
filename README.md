# Mainspring Responsive Image Styles

Does two independent things for Mainspring sites:

1. **`sizes="auto"`**: adds `auto` to the `sizes` attribute of lazy-loaded
   responsive images, so browsers choose the image file from the width the
   image actually renders at. Works on any site as soon as the module is
   enabled, for images rendered through a responsive image style (plain image
   style formatters have no `sizes` and are unaffected). No theme changes and
   no YAML file.
2. **Layout-based responsive image styles** (optional): a Twig function that
   picks a responsive image style from the Layout Paragraphs section and
   column a paragraph sits in, driven by a YAML file in the theme.

You can use the first without the second. Sites that only want `sizes="auto"`
enable the module and do nothing else; see
[Using it only for sizes="auto"](#using-it-only-for-sizesauto).

> **Planned deprecation.** This module is a stopgap. Both features become
> unnecessary over time, and the module will then be removed:
>
> - **`sizes="auto"`** will move into Drupal core:
>   [#3587098](https://www.drupal.org/project/drupal/issues/3587098) adds
>   `auto` to lazy-loaded responsive images. It changes code that only exists
>   in Drupal 11.3+, so Drupal 10 sites keep needing this module until they
>   upgrade. Once a site runs a core version with the fix, this module's
>   change is redundant but harmless (values already starting with `auto` are
>   skipped). Turn it off with the setting below or remove the module.
> - **Layout-based responsive image styles** only exist to give browsers
>   without `sizes="auto"` support (mainly Safari before 27) an accurate
>   `sizes` list. Once Safari 27+ has enough market share on a site's
>   audience (check the site's analytics), replace the per-layout styles with
>   one responsive image style per aspect ratio whose `sizes` is `auto`
>   followed by a simple fallback (e.g. `auto, 100vw`), and drop the Twig
>   function and the YAML file. Eager images such as heroes still need an
>   explicit `sizes`, because browsers ignore `auto` on them.

## Requirements

- Drupal 9 or 10
- Core Responsive Image module (the `sizes="auto"` change applies to its
  `responsive_image` output)
- Layout Paragraphs (a declared dependency; it must be enabled even if you
  don't use the layout feature)

## Install

The repository is public. Add it and require the package in the site's root
`composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/fosterinteractive/mainspring_responsive_image_styles" }
]
```

```bash
composer require fosterinteractive/mainspring_responsive_image_styles:<version>
drush en mainspring_responsive_image_styles
drush cr
drush cex
```

It installs to `web/modules/contrib/mainspring_responsive_image_styles`.
Export config after enabling it so `core.extension.yml` lists the module;
otherwise the next `drush cim` on deploy uninstalls it again.

## sizes="auto"

### What it does

`hook_preprocess_responsive_image()` changes every lazy-loaded responsive
image:

```html
<!-- before -->
<img loading="lazy" srcset="…200w, …400w, …600w, …1200w"
     sizes="(min-width:1280px) 280px, (min-width:600px) 20vw, calc(100vw - 40px)">

<!-- after -->
<img loading="lazy" srcset="…200w, …400w, …600w, …1200w"
     sizes="auto, (min-width:1280px) 280px, (min-width:600px) 20vw, calc(100vw - 40px)">
```

- **Which images:** those rendered with `loading="lazy"`, including images
  core makes lazy by default (images with a width and height and no `loading`
  attribute). Eager images, such as heroes set to `loading="eager"`, are left
  alone, because the HTML spec only allows `auto` on lazy images.
- **Which tags:** the `<img>` (single-image and `<picture>` fallback) and
  every `<source>` inside `<picture>`. If the site uses the WebP module, this
  hook runs before it (both have module weight 0, so they run alphabetically),
  so the WebP `<source>` copies get `auto` too. With core's "Convert to WebP"
  image style effect and the WPF module there are no extra `<source>`
  elements, and WPF only swaps the `<img src>` fallback, so `auto` is kept.
- **Values already starting with `auto`** are left as they are, and empty
  `sizes` values are skipped.
- **No escaping change:** the raw attribute value is read and written, so
  values like `(width >= 768px)` aren't double-escaped.

### Why it helps

Without `auto`, the browser chooses a file from `srcset` using the `sizes`
list, before layout. Theme `sizes` lists often describe the widest case (e.g.
`100vw` or `1150px` for an image that ends up as a 266px card in a 4-column
section), so the browser downloads a far bigger file than it shows.

With `auto`, a supporting browser waits for layout (it can, because the image
is lazy) and uses the image's rendered width instead. Measured on a Mainspring
site at 1440px wide (1x screen):

| Page | Without `auto` | With `auto` |
|---|---|---|
| Home page | 349 KB | 78 KB |
| News listing (11 thumbnails) | 626 KB | 106 KB |
| Test page, 34 images across every section layout | ~2.2 MB | ~0.9 MB |

Browsers that don't support `auto` (before Chrome/Edge 126, Firefox 150,
Safari 27\*) ignore it and use the rest of the list, so they behave exactly as
before. Accurate theme `sizes` values still matter for them.

\* Safari 27 support is partial: SVG images that use `sizes="auto"` together
with CSS `object-fit` can render distorted
([WebKit bug 324661](https://bugs.webkit.org/show_bug.cgi?id=324661), open as
of October 2026). Raster images (JPEG, PNG, WebP) are unaffected. If a site
renders SVGs through a responsive image style with `object-fit`, check them in
Safari 27.

### Checking it in a browser

- The browser multiplies the rendered width by the screen's pixel density. A
  267px image on a 2x (Retina) screen needs 534px, so the 600w file is the
  right choice there, and the 400w file on a 1x screen.
- Browsers reuse a larger file already in the cache instead of downloading a
  smaller one. Hard-reload, or tick **Disable cache** in DevTools → Network,
  before comparing.
- In DevTools, select the `<img>` and read `currentSrc` to see which file
  was chosen.

### Turning it off

Per site, in `settings.php`:

```php
$settings['mainspring_sizes_auto'] = FALSE;
```

Then `drush cr`. The rest of the module is unaffected.

## Using it only for sizes="auto"

Enable the module (see [Install](#install)); that's all. The layout feature
needs no YAML file and no theme changes unless you use it:

| Part | When you don't use it |
|---|---|
| `mainspring_responsive_image_style()` Twig function | Registered but never called. The YAML file is only read when a template calls this function. |
| `mainspring_responsive_image()` Twig function | Registered but never called. |
| `hook_preprocess_paragraph()` | Still runs: for paragraphs inside a Layout Paragraphs section it sets three template variables (below). Nothing reads them, so output doesn't change; the cost is a small amount of work when a page is rendered without the cache. |

## Layout-based responsive image styles (optional)

### Template variables

`hook_preprocess_paragraph()` adds these to every paragraph placed in a Layout
Paragraphs section:

| Variable | Value | Example |
|---|---|---|
| `paragraph_layout` | The section's layout ID | `full_2_col` |
| `paragraph_columns` | The section's `width` class, or `section_layout` if there's no width | `--[zone--5\|zone--5]` |
| `paragraph_region` | The region the paragraph is in | `zone2` |

If the section paragraph can't be loaded, the variables aren't set.

**Known limitation:** the hook reads the width from the section's
`config.classes`. Many sections keep it under `config.additional.classes`
instead (on one site, about 60% of them), so `paragraph_columns` is empty for
those and the Twig function uses the `fallback` entry.

### mainspring_responsive_image_style()

```twig
{% set style = mainspring_responsive_image_style(paragraph_layout, paragraph_columns, 'landscape_3_2', paragraph_region) %}
```

Arguments: layout, columns (the `width` class), the image style the editor
picked, and optionally the region. It returns a responsive image style
machine name, looked up in `<theme>.responsive_image_layouts.yml` in the
default theme's folder:

```yaml
# Example; the style names on the right are the site's own.
# Used when the layout or columns are empty, e.g. a paragraph in a new section
# that hasn't been saved yet: layout ID → columns.
fallback:
  full_1_col: '--[zone--12]'

full_1_col:
  '--[zone--12]':
    landscape_3_2: landscape_3_2_full
full_2_col:
  '--[zone--5|zone--5]':
    # A list gives one style per region: zone1 → first entry, zone2 → second.
    landscape_3_2: [landscape_3_2_half, landscape_3_2_half]
```

A list is indexed by the region number (`zone2` → second entry); without a
region the first entry is used. If nothing matches, it returns an empty string
and, for user 1 only, shows an error message naming the file and the values it
looked for.

The file is required once a template calls this function. Without it (or
without a `fallback` entry), a paragraph with an empty layout or columns
causes a PHP error.

### mainspring_responsive_image()

```twig
{{ mainspring_responsive_image(content.field_image[0]['#item'], style, { class: ['card__image'] }) }}
```

Returns a `responsive_image_formatter` render array for an image field item,
a responsive image style ID and optional `<img>` attributes. Lazy images it
renders get `sizes="auto"` like any other.

## Releasing

The version lives in two places that must match: the git tag (what Composer
installs) and `version:` in `mainspring_responsive_image_styles.info.yml`
(what Drupal shows).

1. Merge the changes to `main`.
2. **Actions → Release → Run workflow**, and enter the new version, e.g.
   `1.1.0` (three numbers, no `v` prefix). Optionally fill in **Summary**: a
   line or two for the top of the release notes.

The workflow sets `version:` in the `.info.yml`, commits "Release 1.1.0" to
`main`, creates and pushes the `1.1.0` tag, and publishes a GitHub release.
The release notes are the summary plus the commit messages since the previous
tag, so write commit messages that read well there. It refuses a malformed
version or one that's already tagged.

You can edit the notes afterwards on the repo's **Releases** page; that
doesn't touch the tag or the code. Composer installs from the tag, not the
release.

**Don't tag by hand,** and don't create a new tag from **Releases → Draft a
new release** (that also skips the `.info.yml` bump). If you have to, bump
`version:` in the `.info.yml` to the same number and commit it first. The
**Check tag** workflow fails any pushed tag that doesn't match the
`.info.yml`.

Don't add a `version` field to `composer.json`; Composer reads the version
from the tag.

## Update hooks

Number update hooks by Drupal's convention, not by the release version:
`mainspring_responsive_image_styles_update_10001()`, then `10002`, and so on.
Drupal runs any hook above the module's stored schema version (`8000` on
sites that have never run one), and ignores the `.info.yml` version when
deciding.
