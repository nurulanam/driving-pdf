# IDTA PDF

Generates two PDFs from WooCommerce order meta when an IDP order is placed:

| Document | Page size | Orientation |
| --- | --- | --- |
| Permit booklet | 210 × 297 mm (A4) | Portrait |
| Permit card | 85.6 × 53.98 mm (ISO/IEC 7810 ID-1) | Portrait |

Base font size is **10 pt**; custom CSS is loaded afterwards and overrides it.

## Install

```bash
cd wp-content/plugins/idta-pdf
composer install --no-dev
```

Activate the plugin, then open **WooCommerce → IDTA PDF** to configure.

If mPDF and `endroid/qr-code` are already autoloaded by your theme, the plugin
uses those instead of its own `vendor/` directory.

## Artwork

The plugin ships no branding. Copy `examples/idta-pdf-artwork.php` into
`wp-content/mu-plugins/` (or paste it into your theme) and adjust the paths.
It wires up:

| Filter | Purpose |
| --- | --- |
| `idta_pdf_booklet_interior_pages` | Scanned booklet pages, before the holder page |
| `idta_pdf_booklet_closing_pages` | Pages after the holder page |
| `idta_pdf_seal_url` | Per-category seal (granted / not granted) |
| `idta_pdf_brand_url` | Booklet `logo`, `signature`, `stamp` |
| `idta_pdf_card_artwork_url` | Card `front`, `back`, `stamp` |

Artwork may be an absolute URL, a site-relative path, or a local path. Anything
unreachable is skipped rather than breaking the document.

## Order meta consumed

Read from the order, exactly as the checkout writes it:

```
_idp_first_name          _idp_country_of_residence    _idp_passport_photo
_idp_middle_name         _idp_driver_license_number   _idp_license_front
_idp_last_name           _idp_country_of_issuance     _idp_license_back
_idp_date_of_birth       _idp_license_category        _idp_signature
_idp_gender              _idp_validity_years
_idp_country_of_birth    _idp_format
```

Derived values:

- **Expiry** = order date + `_idp_validity_years` − 1 day.
- **Convention** = 1968 when `_idp_country_of_issuance` is a Vienna 1968 party, else 1949.
- **Categories** — `"A, B"`, `"A/B"`, `"a b"` all parse to `['A','B']`.
- **`_idp_format`** narrows generation to card-only or booklet-only when it says so.

Remote images (the Cloudflare Worker URLs) are downloaded, cached for 24 h, and
embedded as data URIs, so the PDF engine never makes its own outbound request.

## Behaviour

- Generation is queued via Action Scheduler (falling back to WP-Cron) so
  checkout is never blocked, and runs once per order.
- Documents are written to `uploads/idta-pdf/documents/<year>/<month>/<id>-<token>/`
  and served only through an authorised download endpoint — never linked directly.
- The order edit screen gains an **IDP Documents** panel with download and
  regenerate actions, plus the last error if generation failed.

## Customising

Theme template overrides — copy either file into your theme:

```
your-theme/idta-pdf/booklet.php
your-theme/idta-pdf/card.php
```

Stylesheet cascade, later wins:

1. `@page` geometry
2. base stylesheet (the 10 pt default)
3. `assets/css/{booklet,card}.css`
4. **Custom CSS → Both documents** (settings)
5. **Custom CSS → Booklet only / Card only** (settings)
6. the `idta_pdf_document_css` filter

## Page content, and replacing scans with text

A scanned page costs 600–850 KB; the same page as text costs a few KB. Almost
all of the booklet's size was artwork, so every page that can be text now is.

Conversion is incremental and needs no configuration. Two mechanisms, checked in
this order for each page:

**1. A bespoke template** — create `templates/pages/page-NN.php` and page NN
stops using its scan. Delete the file and the scan comes back. Page numbers come
from the scan filenames, so `final-booklet-IDPA_page-0006.jpg` is page 6 →
`page-06.php` (`page-6.php` also works). Used for the one-off pages:

| Page | Template |
| --- | --- |
| 2 — contracting states | `templates/pages/page-02.php` |
| 3 — language index | `templates/pages/page-03.php` (generated from the language data, so it cannot drift) |

**2. A language entry** — booklet pages 4–22 are one page repeated in nineteen
languages: identical layout, translated strings. They are therefore *not*
nineteen templates. The layout lives once in
`templates/pages/language-page.php`; **all nineteen languages' text lives in
`includes/data/language-pages.php`**, keyed by booklet page number.

Adding or correcting a language is pure data — no layout work. Copy an entry,
translate the strings, set `font` and `rtl`. To add one without editing the
plugin, use the `idta_pdf_language_pages` filter. A page with no entry keeps
rendering from its scan.

Each entry holds: `code`, `label`, `folio`, `font`, `rtl`, `flag`,
`lead_driver`, `lead_valid`, `holder` (5 fields), `categories` (A–E), `notes`
(2 columns), and `exclusion` (title, intro, country, reason, seal, place, date,
signature, footnote).

`font` must cover the script *and* be registered with mPDF:

| Font | Scripts |
| --- | --- |
| `dejavuserif` | Latin, Cyrillic, Turkish, Lithuanian, Vietnamese |
| `xbriyaz` | Arabic |
| `sun-exta` | Chinese, Japanese, Korean |
| `abyssinicasil` | Amharic |
| `freeserif` | Devanagari (Hindi) |
| `garuda` | Thai |

`rtl => true` mirrors the whole page — flag, letter column, stamp and divider
all swap sides, as page 5 (Arabic) is printed.

Only the back cover (page 24) remains a scan: it is genuine artwork — world map,
IDPA logo, UN emblem, QR codes — not text.

Page templates receive the same `$document`, `$data` and `$context` variables as
the main templates, so a converted page can print real order data if wanted.

### Writing a page template

mPDF is not a browser. These behaviours were each confirmed by rendering, and
the page templates are built around them:

- **A `<td>` is a poor container.** Inside one, mPDF ignores `font-family`,
  `text-align` on a child `<p>`, `border-radius`, vertical margins, and nested
  tables. Style the `<td>` itself, and use a `<div>` for anything richer.
- **A `<div>` is a good container.** Tables, floats, `text-align`,
  `border-top`/`border-right` and `border-radius` all behave inside one.
- **Give tables explicit millimetre widths.** A percentage width collapses once
  the table is nested in a sized block, which silently shrinks fill-in rules to
  a few dots.
- **Padding adds to declared height.** For a circle, `height + padding-top` must
  equal `width`, or it renders as an egg.
- **Restate `font-family` on every text-bearing element.** It does not inherit
  reliably, and a hardcoded Latin font renders other scripts as hollow boxes.
- **Verify complex scripts by looking at the rendered page, not extracted text.**
  `pdftotext` cannot reverse Devanagari conjuncts or Thai combining marks, so it
  reports damage that is not there. Rasterise the page and read it instead.
- **The font name must be one mPDF registers.** An unknown name renders every
  glyph as a hollow box — easy to miss in a script you do not read.
  `Language_Pages` checks the name, falls back to the default and logs a warning,
  but only at render time.

## Output

| Document | Pages | Size |
| --- | --- | --- |
| Booklet | 24 — cover, 21 translation/reference pages, holder details, back cover | ~1.2 MB |
| Card | 2 — front, back | ~2 MB |

Every booklet page except the back cover (page 24, which is genuine artwork —
world map, logos, UN emblem) is rendered as text. That took the booklet from
12.7 MB to 1.2 MB, a 91% reduction.

## mPDF quirks worth knowing

Each of these was confirmed by rendering and measuring real PDFs, not assumed.
The stylesheets and image pipeline depend on them:

- **Adjacent-sibling selectors are ignored.** `.idta-page + .idta-page` never
  applied — pagination silently fell back to content overflow, dropping fields
  onto the wrong page. Breaks are carried by the plain `.idta-page--break` class.
- **`background-size` is ignored.** Backgrounds are drawn at natural pixel size
  and cropped to the top-left. Scaling needs mPDF's own
  `background-image-resize` (the card uses `5`, which scales both up and down).
- **A page must fit its own padding.** Content plus padding exceeding 297 mm
  spills the trailing padding onto a blank page, so the holder page is budgeted
  to about 230 mm.
- **Images are passed as local file paths, never data URIs.** Inlining 22
  full-page scans as base64 pushes the HTML past `pcre.backtrack_limit` and mPDF
  refuses to render at all. Remote assets are downloaded to a local cache first,
  so the engine still makes no outbound requests.

## Requirements

PHP 8.0+, WordPress 6.0+, WooCommerce 7.0+, `openssl`. GD is optional and only
needed for the grayscale duplicate portrait.
