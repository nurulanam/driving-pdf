=== IDTA PDF ===
Contributors: am2am
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates the International Driving Permit booklet and ID card as PDFs from WooCommerce order meta, automatically when an order is placed.

== Description ==

Two documents are produced per order:

* **Permit booklet** — 210 × 297 mm (A4) portrait, 24 pages
* **Permit card** — 85.6 × 53.98 mm (ISO/IEC 7810 ID-1) portrait, 2 pages

Both are built from the `_idp_*` meta the checkout writes on the order. Nothing
needs to be entered again — and a shop manager can read or correct every field on
the order screen, under **IDP Driver Details (editable)**, with a thumbnail beside
each uploaded image.

The four uploaded images are stored as a path relative to the bucket the order
came through, so `_idp_order_from` records which one: `idta` or `idpa`. Get that
value wrong and the images cannot be fetched, which the thumbnails will show.

The booklet's nineteen translation pages are set as text rather than scans, in
Latin, Arabic, Cyrillic, Chinese, Japanese, Korean, Amharic, Devanagari and Thai,
each with its own flag. No page is a flattened scan.

**Where the files go.** Documents are written to
`wp-content/uploads/idta-pdf/documents/<year>/<month>/<id>-<token>/` and served
only through an authorised endpoint — the directory is protected and files are
never linked directly. Access is granted to a shop manager, or to the customer
holding the order key.

== Installation ==

This archive is complete: mPDF, the QR library and every font needed are already
inside. No Composer step, no build step.

1. **Plugins → Add New → Upload Plugin**, choose `idta-pdf.zip`, install, activate.
   If the upload is refused for size, unzip it into `wp-content/plugins/` over
   SFTP instead and activate from the Plugins screen.
2. Open **WooCommerce → IDTA PDF** and set the QR verification secret. Leave the
   rest at its defaults to begin with.
3. Place a test order carrying `_idp_*` meta, then open it. The **IDP Documents**
   panel should offer both files.

Requires the `openssl` PHP extension, which standard hosting has. GD is optional
and only used for the greyscale duplicate portrait.

= Upgrading =

Deactivate, replace the plugin directory, reactivate. Settings and already
generated documents are kept; they live in options and uploads, not in the plugin
directory. Nothing is deleted until the plugin is uninstalled.

== Frequently Asked Questions ==

= What are the /idp/ and /show-details/ pages? =

The two pages the QR codes point at, created empty on activation. `/idp/` offers
the permit PDF; `/show-details/` lists the holder's details and licence scans.
Both need the `entry_key` token from the printed code — without it, or with one
meant for the other page, they return 404 and show nothing.

They render as a standalone page without the theme's header and footer, on
purpose: they are opened on a phone immediately after scanning. Don't add content
to them in the editor — the markup comes from the plugin and page content is
ignored.

If the pages are missing, deactivate and reactivate the plugin.

= When are the PDFs generated? =

On checkout, queued through Action Scheduler so the customer is never kept
waiting, and once per order. WP-Cron is used if Action Scheduler is unavailable.
You can add further triggers under **Also generate when order becomes** — useful
if payment confirmation, not checkout, is when an order becomes real for you.

= An order has no PDFs. =

The WooCommerce order list has a **PDFs** column at the right with a **Generate
Permit** / **Generate Card** button for anything missing, a regenerate icon, and —
when a document failed — a red warning icon whose tooltip is the actual error.
Hover it: that message is the fastest way to find out what went wrong.

Orders with no `_idp_*` meta show a dash: they are not IDP orders and are skipped.

= Nothing generates at all. =

Check that WP-Cron is running, then look in **WooCommerce → Status → Logs**,
source `idta-pdf`. Almost always this is a queue that is not draining rather than
the plugin.

= Can the PDFs be emailed to the customer? =

Yes, under **Attach to emails**, but it is off by default: the booklet is around
1.5 MB and the card 0.65 MB, and some mail providers reject or silently drop that.
The download link is the safer route.

= Are the translations verified? =

**No.** The legal text on the nineteen translation pages was transcribed from the
scanned originals and has not been proof-read by native readers. Have each page
checked before anything goes to print. All nineteen languages live in one file,
`includes/data/language-pages.php`, so corrections are quick and need no layout
work.

= Can the layout be changed? =

Copy `templates/booklet.php` or `templates/card.php` into
`your-theme/idta-pdf/` and edit there, so upgrades do not overwrite it. For
smaller changes, the settings screen has Custom CSS boxes that load after the
plugin's own stylesheets and override them. Base font size is 10 pt.

`README.md` in the plugin directory documents the filters, the page templates and
the mPDF constraints the layout works within.

== Screenshots ==

1. The PDFs column in the WooCommerce order list.
2. The IDP Documents panel on the order edit screen.
3. The settings screen.

== Changelog ==

= 1.0.0 =
* Booklet (A4) and card (ID-1) generated automatically on order placement.
* Every page rendered from text and artwork instead of scans, taking the booklet
  from 12.7 MB to about 1.5 MB.
* Contracting-states list and language index generated from the same data as the
  pages, so the index cannot drift out of step.
* Protected storage with an authorised download endpoint; no direct file links.
* QR verification links, AES-256-CBC encrypted.
* Order list column and order-screen panel with manual generate and regenerate.
* Optional email attachment, off by default.
* HPOS compatible.
