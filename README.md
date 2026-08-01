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
| `idta_pdf_brand_url` | Booklet `cover_logo`, `logo`, `signature`, `stamp`, `wordmark`, `un_emblem`, `qr_left`, `qr_right` |
| `idta_pdf_flag_url` | Per-language flag on a translation page |
| `idta_pdf_brand_site`, `idta_pdf_brand_email` | Contact details on the back cover |
| `idta_pdf_card_artwork_url` | Card `front`, `back`, `stamp` |

Artwork may be an absolute URL, a site-relative path, or a local path. Anything
unreachable is skipped rather than breaking the document.

## Order meta consumed

Read from the order, exactly as the checkout writes it:

```
_idp_order_from          _idp_country_of_residence    _idp_passport_photo
_idp_first_name          _idp_driver_license_number   _idp_license_front
_idp_middle_name         _idp_country_of_issuance     _idp_license_back
_idp_last_name           _idp_license_category        _idp_signature
_idp_date_of_birth       _idp_validity_years
_idp_gender              _idp_format
_idp_country_of_birth
```

A shop manager can read and correct all of these on the order screen, under **IDP
Driver Details (editable)**, with a thumbnail beside each of the four images.
Saving only updates the order — use **Regenerate** in the IDP Documents panel to
rebuild the PDFs afterwards.

### Uploaded images and `_idp_order_from`

The four image fields hold a path relative to the bucket the order was taken
through, not a full URL:

```
2026/07/29/084250-394/portrait.jpg
2026/07/30/162022-151/license-back-riyad.jpg
```

`_idp_order_from` says which bucket, and so which base URL to put back:

| `_idp_order_from` | Base URL |
| --- | --- |
| `idta` | `https://idta-upload.shamim66ewu.workers.dev/files/` |
| `idpa` | `https://pub-1c2e77688359483ca692ff2d8369b41b.r2.dev/` |

Compared case-insensitively. Add another front end with the
`idta_pdf_order_sources` filter rather than editing the plugin.

Two kinds of stored value are already complete and are left alone: an absolute
URL, which is how orders placed before `_idp_order_from` existed stored these;
and a path into this site's own directories (`/wp-content/…`). Anything else is
treated as bucket-relative, including a value that arrives with a leading slash.

An order that stores a relative path but names no recognised source falls back to
`idta` — override with the `idta_pdf_default_order_source` filter, or return an
empty string to have the image skipped and a warning logged instead.

Derived values:

- **Expiry** = order date + `_idp_validity_years` − 1 day.
- **Convention** = 1968 when `_idp_country_of_issuance` is a Vienna 1968 party, else 1949.
- **Categories** — `"A, B"`, `"A/B"`, `"a b"` all parse to `['A','B']`.
- **`_idp_format`** narrows generation to card-only or booklet-only when it says so.

Remote images are downloaded once, cached for 24 h, and handed to the engine as
local file paths, so the PDF engine never makes its own outbound request. The
bytes are sniffed for a real image signature before being cached, without any
admin-only function, because generation runs on the frontend during checkout.

## Public pages

Two pages are created on activation, empty, so the URLs the QR codes encode
resolve:

| Page | Slug | Shows |
| --- | --- | --- |
| Permit | `/idp/` | A download link for the permit PDF, and the card when the order has one |
| Details | `/show-details/` | Name, birth country, DOB, gender, licence types, and the licence scans |

Both are reached as `?entry_key=<token>`, an AES-256-CBC token of the order ID
signed with a per-page secret. The two secrets differ, so a permit link will not
open the details page or the reverse.

They are served as a **standalone HTML document** — the theme's header, navigation
and footer are deliberately bypassed, because these pages are opened on a phone
straight after scanning a printed code. That also makes them look the same on
every site, and means the stylesheet is inlined (there is no `wp_head()` for an
enqueued one to print into). `noindex` and no-cache headers are sent, since the
content is personal.

An unrecognised, missing or wrong-context token returns 404 with a generic
message and reveals nothing about which orders exist.

Override either page by copying it into your theme:

```
your-theme/idta-pdf/public/permit.php
your-theme/idta-pdf/public/details.php
```

Restyle without touching markup through the `idta_pdf_public_css` filter.

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
| 24 — back cover | `templates/pages/page-24.php` |

**2. A language entry** — booklet pages 4–22 are one page repeated in nineteen
languages: identical layout, translated strings. They are therefore *not*
nineteen templates. The layout lives once in
`templates/pages/language-page.php`; **all nineteen languages' text lives in
`includes/data/language-pages.php`**, keyed by booklet page number.

Adding or correcting a language is pure data — no layout work. Copy an entry,
translate the strings, set `font` and `rtl`. To add one without editing the
plugin, use the `idta_pdf_language_pages` filter. A page with no entry keeps
rendering from its scan.

Each entry holds: `code`, `label`, `folio`, `font`, `weight`, `rtl`, `flag`,
`flag_image`, `lead_driver`, `lead_valid`, `holder` (5 fields), `categories`
(A–E), `notes` (2 columns), and `exclusion` (title, intro, country, reason,
seal, place, date, signature, footnote).

`font` must cover the script *and* be registered with mPDF:

| Font | Scripts |
| --- | --- |
| `dejavuserif` | Latin, Cyrillic, Turkish, Lithuanian, Vietnamese |
| `xbriyaz` | Arabic |
| `sun-exta` | Chinese, Japanese |
| `unbatang` | Korean |
| `freeserif` | Devanagari (Hindi), Amharic |
| `garuda` | Thai |

`abyssinicasil` (Ethiopic) is also bundled and registered, but isn't the default
for Amharic — its strokes read heavier and rounder than freeserif's. Available
for a custom page via `idta_pdf_language_pages` if preferred.

`weight => 'bold'` thickens the face. mPDF's bundled CJK and Ethiopic fonts ship
in one hairline weight that prints far lighter than the scans, so those pages ask
for bold and mPDF strokes the outline to fake the missing weight. The stroke
width is set by `falseBoldWeight` in the renderer — mPDF's default of 5 clogs
small ideographs, so it runs at 3.

`rtl => true` mirrors the whole page — flag, letter column, stamp and divider
all swap sides, as page 5 (Arabic) is printed.

The vehicle-category letters A–E are the one element identical on all nineteen
pages, so they are always set in `dejavuserif` whatever script font the page
uses.

### Flags

Flags come from `assets/img/Flag/`, named after the language label the page
prints — `Français.png` is the flag on the French page. All nineteen are bundled.

Override one file at a time with the `idta_pdf_flag_url` filter, or set
`flag_image` on a language entry to a path or URL. With no artwork at all a page
falls back to `flag` (three colours drawn as a vertical tricolour — the only flag
a three-cell strip renders honestly), and failing that prints the language name
rather than misrepresent a flag.

### Registering another font

mPDF bundles one face per script, and for some scripts it is a poor match — the
Ethiopic face is the only one available and does not match the printed booklet.
Point mPDF at your own file instead:

```php
add_filter( 'idta_pdf_font_directories', fn( $dirs ) => array_merge( $dirs, array( '/srv/fonts/' ) ) );

add_filter(
	'idta_pdf_font_data',
	fn( $fonts ) => array_merge(
		$fonts,
		array( 'notoserifethiopic' => array( 'R' => 'NotoSerifEthiopic-Regular.ttf' ) )
	)
);
```

Then name it as the page's `font`. Both filters add to mPDF's own defaults rather
than replacing them, and a name registered this way is accepted by
`Language_Pages` too.

No booklet page is a scan any more. The back cover is the one page that is mostly
artwork rather than text, so it is assembled from the individual map, logo, emblem
and QR files instead of one flattened image.

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
- **`position: absolute` is ignored.** A folio cannot be pinned to the bottom of
  the page; the language pages place it by giving the content above it a fixed
  height, which also keeps it at the same spot on all nineteen.
- **A cell cannot shrink to its text without a cost.** The only layout mPDF
  offers is a rule cell at `width: 100%`, which it then reads as an overflow and
  answers by shrinking the *whole table's type* — so "Signature" printed a size
  smaller than "Lieu" beside it. `Language_Pages::label_width_mm()` measures the
  label instead and both columns get an explicit millimetre width.
- **`white-space: nowrap` is not honoured for Arabic** in an auto-width cell:
  mPDF set the label one character per line and pushed the page onto an extra
  sheet. Explicit widths avoid this too.
- **Floats do not have text flow beside them.** A floated label with a bordered
  block as its sibling puts the rule on the next line, so a fill-in rule has to
  be a table.
- **An empty inline-block draws nothing** — not even its own border. The holder
  page's exclusion rules were bordered spans and printed as bare numerals with
  nothing to write on. Any fill-in rule is a table cell with a bottom border.

## Output

| Document | Pages | Size |
| --- | --- | --- |
| Booklet | 24 — cover, 21 translation/reference pages, holder details, back cover | ~1.5 MB |
| Card | 2 — front, back | ~0.65 MB |

Every booklet page is rendered from text and artwork rather than a page scan,
which took the booklet from 12.7 MB to about 1.5 MB.

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
