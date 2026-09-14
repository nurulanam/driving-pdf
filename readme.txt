=== IDTA PDF ===
Contributors: am2am
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 11.0
Stable tag: 1.8.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Renders the International Driving Permit booklet and ID card from WooCommerce order meta, on demand, and emails the customer when their permit is ready.

== Description ==

Four documents can be produced per order:

* **Permit booklet** — 210 × 297 mm (A4) portrait, 24 pages
* **Permit card** — 85.6 × 53.98 mm (ISO/IEC 7810 ID-1) portrait, 2 pages
* **Permit print** — A5, the holder's details alone, for printing onto pre-printed
  booklet stock
* **Card front (BMP)** — 1011 × 638 px, 24-bit at 300 dpi, for a direct-to-card
  printer such as a Zebra ZC300

The first two go to the customer. The last two are production files and stay on
the admin screens.

All are built from the `_idp_*` meta the checkout writes on the order. Nothing
needs to be entered again — and a shop manager can read or correct every field on
the order screen, under **IDP Driver Details (editable)**, with a thumbnail beside
each uploaded image.

All four uploaded images land in one shared folder, so only that folder is
stored (`_idp_assets`), as a path relative to the bucket the order came through —
`_idp_order_from` records which one, `idta` or `idpa`. Get that value wrong and
none of the images can be fetched, which the thumbnails on the order screen will
show.

The booklet's nineteen translation pages are set as text rather than scans, in
Latin, Arabic, Cyrillic, Chinese, Japanese, Korean, Amharic, Devanagari and Thai,
each headed by the language's own name. No page is a flattened scan.

**Nothing is stored.** A document is rendered when its link is opened and streamed
straight to the browser, so every link is current and no copy is left on the
server. Access is granted to a shop manager, or to the customer holding the order
key — and, for the customer, only once their permit has been released.

**Release rules.** A customer's links begin working a set time after payment:
five minutes for a rush order, four hours otherwise, both configurable, along with
which products count as rush. A shop manager's own links always work immediately.
Once the customer has been emailed their links, the order stays released — no
later status change or settings edit takes it back.

**The permit-ready email.** When the wait is up, the customer is emailed buttons
for their booklet and card, plus anything else virtual on the order. It appears in
WooCommerce → Settings → Emails as *Permit ready*, so its subject, heading and
on/off switch sit where you already look for them.

== Installation ==

This archive is complete: mPDF, the QR library and every font needed are already
inside. No Composer step, no build step.

1. **Plugins → Add New → Upload Plugin**, choose `idta-pdf.zip`, install, activate.
   If the upload is refused for size, unzip it into `wp-content/plugins/` over
   SFTP instead and activate from the Plugins screen.
2. Open **WooCommerce → IDTA PDF** and set the QR verification secret. Leave the
   rest at its defaults to begin with.
3. Place a test order carrying `_idp_*` meta, then open it. The **IDP Documents**
   panel should link every document the order offers.

Requires the `openssl` PHP extension, which standard hosting has. GD is needed for
the greyscale duplicate portrait and the card bitmap; the bitmap also needs
Imagick, Ghostscript or `pdftoppm`, and offers itself only where one is present.
**WooCommerce → IDTA PDF → Status** reports what this server can do.

= Upgrading =

**Delete the old plugin directory before replacing it**, rather than uploading
over the top. An upload overwrites the files in the new copy and removes nothing
else, so a class deleted from the plugin stays on the server and keeps registering
its hooks. Settings and orders are untouched by a delete — they live in options
and in the database.

Clear OPcache afterwards if your host runs it. WordPress reads the version number
straight from disk but executes the cached bytecode, so a stale cache shows the
new version while running the old code.

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

When their link is opened. Nothing is built in advance and nothing is stored, so
there is no queue to drain and no document that can be out of date — a link always
renders the order as it stands at that moment.

= When can the customer download theirs? =

After the wait set under **IDTA PDF → Release**: five minutes for an order
containing one of your rush products, four hours otherwise. The wait is measured
from payment, so it is the same whether they paid at checkout or followed a pay
link days later. Your own links work immediately.

= An order shows a clock instead of a tick. =

Hover it. It says which of the three conditions is holding the order back — not
paid, a status you do not release documents for, or the wait itself, with the time
it ends. Your own links above work regardless.

= The customer never received the permit email. =

Look in **WooCommerce → Status → Logs**, source `transactional-emails`: WooCommerce
records every email there with its outcome. Then **WooCommerce → Status →
Scheduled Actions**, group `idta-pdf`, where each action logs what it did — sent,
failed, or skipped and why.

If the log says it sent, the message reached your mail transport and the question
is delivery: check your SMTP provider's own log and the customer's spam folder.
**Resend order emails** on the order screen sends it again.

= Can the PDFs be attached to emails instead? =

No. There is no file to attach, and a booklet exceeds what most mail servers
accept. The permit-ready email carries links, which also means the customer always
opens the current document.

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

1. The PDFs column in the WooCommerce order list, with the release marker.
2. The IDP Documents panel on the order edit screen.
3. The settings screen.
4. The permit-ready email.

== Changelog ==

= 1.8.2 =
* Each scheduled action now logs its own outcome, so Scheduled Actions reads one
  line per order rather than a uniform "complete".

= 1.8.1 =
* Once the permit-ready email has been sent, the order stays released for good: a
  later status change, a longer wait or a refund can no longer break links the
  customer already holds.
* Fixed a fatal that would have broken every customer download: an order-screen
  reference loaded a class extending WC_Email before WooCommerce had defined it.

= 1.8.0 =
* Fixed completing an order revoking the customer's permit. The status setting
  used to mean "generate at" and was reused to mean "may download"; a list that
  omitted Completed then withheld from every completed order. Paid statuses are
  added to the saved list once, on upgrade.
* Held-back orders now say which condition is holding them, instead of always
  reporting a wait that may long since have passed.

= 1.7.3 =
* The permit-ready email refuses to send an empty message, so a missing template
  can no longer produce a successful send that delivers nothing.

= 1.7.2 =
* Fixed all outgoing mail failing when an earlier version's email-attachments
  class was left on the server by an in-place update. Orphaned files are now
  removed on upgrade.
* A failed send no longer marks an order as notified: three attempts, recorded
  separately from success.

= 1.7.0 =
* Permit products renamed wherever an order line is shown, at the property getter
  so every template, email and export agrees.

= 1.6.0 =
* Permit-ready email, as a WooCommerce email with Booklet and Digital Card
  buttons and the order's other virtual lines. Queued with Action Scheduler at the
  release moment.

= 1.5.0 =
* Documents are no longer stored. Each is rendered when its URL is opened and
  streamed out; previously stored files are deleted on upgrade.
* Release rules: a customer's links begin working a set time after payment — five
  minutes for a rush order, four hours otherwise.
* Email attachments removed: there is no file to attach, and they delivered
  documents ahead of the release wait.

= 1.4.0 =
* Settings screen rebuilt: six tabs, grouped panels, the tab survives saving, and
  a Status tab reporting what the server can do.
* Fixed the QR base URL and both signing secrets being wiped by every save — they
  were sanitised but had no fields.

= 1.3.0 =
* Generation delay by product: rush orders in minutes, everything else in hours.

= 1.2.0 =
* Card front exportable as a 24-bit RGB bitmap at 300 dpi for a direct-to-card
  printer.
* Card text set to pure black so a ZC300 routes it to the resin panel; near-black
  printed through the dye panels and looked faded.

= 1.1.0 =
* Permit print (A5), the holder's details alone, for overprinting pre-printed
  booklet stock.
* Brand-aware thank-you redirect carrying the order reference, transaction id,
  currency and value.

= 1.0.0 =
* Booklet (A4) and card (ID-1) generated automatically on order placement.
* Every page rendered from text and artwork instead of scans, taking the booklet
  from 12.7 MB to about 1.5 MB.
* Contracting-states list and language index generated from the same data as the
  pages, so the index cannot drift out of step.
* Protected storage with an authorised download endpoint; no direct file links.
  (Storage was removed in 1.5.0 — documents are now rendered on request.)
* QR verification links, AES-256-CBC encrypted.
* Order list column and order-screen panel with manual generate and regenerate.
  (Both removed in 1.5.0; there is nothing to rebuild.)
* Optional email attachment, off by default. (Removed in 1.5.0.)
* HPOS compatible.
