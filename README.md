# mainspring_responsive_image_styles
## sizes="auto" for lazy-loaded responsive images

Lazy responsive images (`loading="lazy"`, including core's default for images
with width/height) get `auto, ` prepended to `sizes` on the `<img>` and every
`<source>`. Browsers that support it (Chrome/Edge 126+, Firefox 150+,
Safari 27+) pick the srcset candidate from the image's rendered width; others
ignore `auto` and use the rest of the list unchanged. Eager images (e.g.
heroes) are not touched. Values that already start with `auto` are left as-is.

Opt out per site in settings.php:

    $settings['mainspring_sizes_auto'] = FALSE;
