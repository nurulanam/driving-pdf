#!/usr/bin/env bash
#
# Build the installable plugin archive.
#
#   bin/build-plugin-zip.sh [output-directory]
#
# Produces idta-pdf.zip, which unpacks to a single idta-pdf/ directory ready to
# drop into wp-content/plugins/ with no further steps.
#
# Most of an unpruned build is weight the plugin never uses: mPDF ships fonts for
# about forty scripts, endroid ships a 16MB font only its label feature needs, and
# the scanned pages are only still present because they are the booklet's page
# manifest — all but the back cover now render as text. This script strips those,
# and includes/class-fonts.php decides which fonts stay so the archive and the
# renderer cannot disagree.

set -euo pipefail

plugin_dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
out_dir="${1:-$plugin_dir}"
work="$( mktemp -d )"
stage="$work/idta-pdf"

trap 'rm -rf "$work"' EXIT

command -v zip >/dev/null || { echo "zip is required." >&2; exit 1; }
command -v php >/dev/null || { echo "php is required." >&2; exit 1; }

if [ ! -d "$plugin_dir/vendor/mpdf/mpdf" ]; then
	echo "vendor/ is missing. Run: composer install --no-dev" >&2
	exit 1
fi

echo "Staging..."

# test.php is a scratch file carrying its own "Plugin Name:" header. Shipping it
# would make WordPress list a second plugin inside this one, and activating it
# would register a duplicate driver-details metabox fighting the real one over the
# same meta. Its functionality now lives in includes/class-order-fields.php.
rsync -a \
	--exclude '.git' \
	--exclude '.github' \
	--exclude '.gitignore' \
	--exclude 'bin/' \
	--exclude 'old/' \
	--exclude 'test.php' \
	--exclude '*.zip' \
	--exclude '*.log' \
	--exclude '.DS_Store' \
	"$plugin_dir/" "$stage/"

# ---- fonts: keep only the faces includes/class-fonts.php names ----------------

keep_list="$work/keep.txt"

STAGE="$stage" php -r '
	define( "ABSPATH", __DIR__ );
	require getenv( "STAGE" ) . "/includes/class-fonts.php";
	echo implode( "\n", IDTA\PDF\Fonts::files() ), "\n";
' > "$keep_list"

fonts_dir="$stage/vendor/mpdf/mpdf/ttfonts"
kept=0
dropped=0

while IFS= read -r -d '' file; do
	name="$( basename "$file" )"

	if grep -Fxq "$name" "$keep_list"; then
		kept=$(( kept + 1 ))
	else
		rm -f "$file"
		dropped=$(( dropped + 1 ))
	fi
done < <( find "$fonts_dir" -type f -print0 )

echo "Fonts: kept $kept, dropped $dropped."

# Fail loudly rather than ship an archive that cannot render a script.
while IFS= read -r name; do
	[ -n "$name" ] || continue

	if [ ! -f "$fonts_dir/$name" ]; then
		echo "Font named by Fonts::files() is not in vendor: $name" >&2
		exit 1
	fi
done < "$keep_list"

# ---- other dependencies ------------------------------------------------------

# Only endroid's label feature loads these, and no label is ever drawn.
rm -rf "$stage/vendor/endroid/qr-code/assets"

find "$stage/vendor" -type d \( -name test -o -name tests -o -name docs -o -name .github \) -prune -exec rm -rf {} + 2>/dev/null || true
find "$stage/vendor" -type f \( -name '*.md' -o -name 'phpstan*' -o -name '*.dist' -o -name '.php-cs-fixer*' \) -delete 2>/dev/null || true

# ---- artwork -----------------------------------------------------------------

# Two separate jobs here. The scans: page 24 is the only one still printed, and
# the rest exist so the page list and the fallback stay intact, so they are
# recompressed hard rather than removed. The card guilloche: shipped at about
# 474dpi and embedded into every card, which is most of why a generated card PDF
# was heavy enough that email attachment is off by default.
if php -r 'exit( extension_loaded( "gd" ) ? 0 : 1 );'; then
	STAGE="$stage" php -r '
		$stage = getenv( "STAGE" );

		foreach ( glob( $stage . "/assets/img/pages/*.jpg" ) as $file ) {
			$image = @imagecreatefromjpeg( $file );

			if ( false === $image ) {
				continue;
			}

			$printed = false !== strpos( $file, "page-0024" );

			imagejpeg( $image, $file, $printed ? 82 : 45 );
			imagedestroy( $image );
		}

		/*
		 * The card guilloche. Shipped at 1597px for an 85.6mm panel — about
		 * 474dpi — and base64-embedded into every generated card, so it alone
		 * accounted for most of the weight of a card PDF. 1100px is still over 300dpi.
		 * Its alpha has to survive: the panel is semi-transparent and prints
		 * muddy if flattened.
		 */
		$file  = $stage . "/assets/img/card/font-bottom-bg.png";
		$image = is_file( $file ) ? @imagecreatefrompng( $file ) : false;

		if ( false !== $image ) {
			if ( imagesx( $image ) > 1100 ) {
				$scaled = imagescale( $image, 1100 );

				if ( false !== $scaled ) {
					imagedestroy( $image );
					$image = $scaled;
				}
			}

			imagealphablending( $image, false );
			imagesavealpha( $image, true );
			imagepng( $image, $file, 9 );
			imagedestroy( $image );
		}
	'
	echo "Artwork recompressed."
else
	echo "GD missing; artwork left as-is." >&2
fi

# The pre-composed card faces: both faces are now drawn from the parts in
# assets/img/card, so nothing references these 3.8MB of artwork. The two card
# reference renders are design sources, not shipped assets.
rm -f "$stage/assets/img/white-front.jpg"  "$stage/assets/img/white-back.jpg" \
      "$stage/assets/img/white-front.jpeg" "$stage/assets/img/white-back.jpeg" \
      "$stage/assets/img/card/front-demo.png" "$stage/assets/img/card/back-demo.png" \
      "$stage/assets/img/card/portrait.jpg"

# The pre-printed booklet pages the print copy is registered against. Design
# references for measuring positions, loaded by nothing, and 1.4MB together.
rm -f "$stage/assets/img/page-01.jpg" "$stage/assets/img/page-23.jpg"

# ---- archive -----------------------------------------------------------------

mkdir -p "$out_dir"
rm -f "$out_dir/idta-pdf.zip"

( cd "$work" && zip -qr9 "$out_dir/idta-pdf.zip" idta-pdf )

echo
echo "Built $out_dir/idta-pdf.zip"
du -sh "$out_dir/idta-pdf.zip" | awk '{print "Archive:  " $1}'
du -sh "$stage" | awk '{print "Unpacked: " $1}'
