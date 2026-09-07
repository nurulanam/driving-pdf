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

# ---- fonts: subset the faces that only ever set fixed strings ----------------

# Three of the bundled scripts are selected by nothing but the translation-page
# template, and every string they set is fixed text from
# includes/data/language-pages.php. Subsetting them to exactly those characters
# takes 8.9MB down to about 150KB, and renders pixel-for-pixel identically —
# Arabic joining included, because the layout tables are retained.
#
# sun-exta is subset too, which is why it is no longer in Fonts::FALLBACKS: at
# 21.9MB it was 46% of the plugin, and 50,112 glyphs to print 276 characters. It
# can no longer stand in for CJK that arrives in order data — see the note on
# Fonts::FALLBACKS.
#
# freeserif is left whole on purpose. It is still a fallback, so it has to cover
# more than these pages.
if command -v pyftsubset >/dev/null; then
	chars="$work/chars"
	mkdir -p "$chars"

	# One file of characters per font file, taken from the language data.
	STAGE="$stage" CHARS="$chars" php -r '
		define( "ABSPATH", __DIR__ );

		$pages = require getenv( "STAGE" ) . "/includes/data/language-pages.php";
		$text  = array();
		$buf   = "";

		$walk = function ( $value ) use ( &$walk, &$buf ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					$walk( $item );
				}
			} elseif ( is_string( $value ) ) {
				$buf .= $value;
			}
		};

		foreach ( $pages as $page ) {
			$family = isset( $page["font"] ) ? $page["font"] : "dejavuserif";
			$buf    = "";

			$walk( $page );

			$text[ $family ] = ( isset( $text[ $family ] ) ? $text[ $family ] : "" ) . $buf;
		}

		require getenv( "STAGE" ) . "/includes/class-fonts.php";

		foreach ( IDTA\PDF\Fonts::bundled() as $family => $faces ) {
			if ( ! in_array( $family, array( "unbatang", "xbriyaz", "garuda", "sun-exta" ), true ) ) {
				continue;
			}

			foreach ( $faces as $file ) {
				if ( is_string( $file ) && isset( $text[ $family ] ) ) {
					file_put_contents( getenv( "CHARS" ) . "/" . $file . ".txt", $text[ $family ] );
				}
			}
		}
	'

	before=0
	after=0

	for charfile in "$chars"/*.txt; do
		[ -f "$charfile" ] || continue

		font="$fonts_dir/$( basename "$charfile" .txt )"

		[ -f "$font" ] || continue

		before=$(( before + $( stat -c%s "$font" ) ))

		# --layout-features keeps the shaping tables, which is what lets the
		# Arabic still join; without them the subset renders unjoined letters.
		if pyftsubset "$font" --output-file="$font.sub" --text-file="$charfile" \
			--layout-features='*' --glyph-names --notdef-outline --no-hinting 2>/dev/null
		then
			mv "$font.sub" "$font"
		else
			rm -f "$font.sub"
			echo "Could not subset $( basename "$font" ); left whole." >&2
		fi

		after=$(( after + $( stat -c%s "$font" ) ))
	done

	if [ "$before" -gt 0 ]; then
		echo "Fonts subset: $(( before / 1024 ))KB -> $(( after / 1024 ))KB."
	fi
else
	echo "pyftsubset missing; script fonts left whole (about 31MB more)." >&2
fi

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
# assets/img/card, so nothing references these of artwork. front-full.jpg is the
# printed design the old card CSS was measured against, and is referenced by
# nothing now either. The card reference renders are design sources.
rm -f "$stage/assets/img/front-full.jpg" \
      "$stage/assets/img/white-front.jpg"  "$stage/assets/img/white-back.jpg" \
      "$stage/assets/img/white-front.jpeg" "$stage/assets/img/white-back.jpeg" \
      "$stage/assets/img/card/front-demo.png" "$stage/assets/img/card/back-demo.png" \
      "$stage/assets/img/card/portrait.jpg"

# The pre-printed booklet pages the print copy is registered against. Design
# references for measuring positions, loaded by nothing, and 1.4MB together.
# assets/img/demos holds the design references the layouts were measured
# against — the blank stock and photographs of test prints. Nothing loads them.
rm -rf "$stage/assets/img/demos"

# ---- archive -----------------------------------------------------------------

mkdir -p "$out_dir"
rm -f "$out_dir/idta-pdf.zip"

( cd "$work" && zip -qr9 "$out_dir/idta-pdf.zip" idta-pdf )

echo
echo "Built $out_dir/idta-pdf.zip"
du -sh "$out_dir/idta-pdf.zip" | awk '{print "Archive:  " $1}'
du -sh "$stage" | awk '{print "Unpacked: " $1}'
