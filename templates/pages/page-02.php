<?php
/**
 * Booklet page 2 — list of contracting states.
 *
 * A text rebuild of `assets/img/pages/final-booklet-IDPA_page-002.jpg`, the
 * heaviest scan in the booklet at ~850 KB.
 *
 * This page has its own layout rather than using the shared language layout, so
 * it lives in a numbered template. The states are held as one alphabetical list
 * and packed into the scan's four columns below, rather than written out column
 * by column: a corrected name moves between columns, and by-hand columns go
 * silently out of balance when it does.
 *
 * Override by copying to `idta-pdf/pages/page-02.php` in your theme.
 *
 * @var \IDTA\PDF\Booklet_Document $document Document instance.
 * @var \IDTA\PDF\Order_Data       $data     Order data.
 * @var array<string,mixed>        $context  Template context.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Contracting states, alphabetically.
 *
 * One entry per state. A name too wide for the 40.5mm column is given as the
 * lines it prints on, and the packer below keeps those lines together in one
 * column. Writing a continuation line as its own entry is what put BOSNIA and
 * HERZEGOVINA in two different columns as though they were two countries.
 *
 * "St." sorts as "Saint", which is where the printed booklet files it.
 *
 * @var array<int,string|string[]> $states
 */
$states = array(
	'AFGHANISTAN',
	'ALBANIA',
	'ALGERIA',
	'ANDORRA',
	'ANGOLA',
	'ANGUILLA',
	'ANTIGUA',
	'ARGENTINA',
	'ARMENIA',
	'ARUBA',
	'AUSTRALIA',
	'AUSTRIA',
	'AZERBAIJAN',
	'BAHAMAS',
	'BAHRAIN',
	'BANGLADESH',
	'BARBADOS',
	'BELARUS',
	'BELGIUM',
	'BELIZE',
	'BENIN',
	'BHUTAN',
	'BOLIVIA',
	'BOSNIA & HERZEGOVINA',
	'BOTSWANA',
	'BRAZIL',
	'BRUNEI',
	'BULGARIA',
	'BURKINA FASO',
	// Cabo Verde in all languages since 2013, at the state's own request.
	'CABO VERDE',
	'CAMBODIA',
	'CAMEROON',
	'CANADA',
	'CAYMAN ISLANDS',
	'CENTRAL AFRICAN REP.',
	'CHAD',
	'CHILE',
	'COLOMBIA',
	'COMOROS',
	'CONGO',
	'COSTA RICA',
	// The state's own name. "Ivory Coast" was listed alongside it as though the
	// two were separate countries.
	'CÔTE D\'IVOIRE',
	'CROATIA',
	'CUBA',
	'CURAÇAO',
	'CYPRUS',
	'CZECH REP.',
	'DENMARK',
	'DJIBOUTI',
	'DOMINICA',
	'DOMINICAN REP.',
	'ECUADOR',
	'EGYPT',
	'EL SALVADOR',
	'EQUATORIAL GUINEA',
	'ESTONIA',
	// Renamed from Swaziland in 2018, which moves it from S to E.
	'ESWATINI',
	'FIJI',
	'FINLAND',
	'FRANCE',
	'FRENCH POLYNESIA',
	'FRENCH TERRITORIES',
	'GABON',
	'GAMBIA',
	'GEORGIA',
	'GERMANY',
	'GHANA',
	'GIBRALTAR',
	'GREECE',
	'GRENADA',
	'GUATEMALA',
	'GUERNSEY',
	'GUINEA',
	'GUINEA-BISSAU',
	'GUYANA',
	'HAITI',
	'HONDURAS',
	'HONG KONG',
	'HUNGARY',
	'ICELAND',
	'INDIA',
	'INDONESIA',
	'IRAN',
	'IRELAND',
	'ISRAEL',
	'ITALY',
	'JAMAICA',
	'JAPAN',
	'JERSEY',
	'JORDAN',
	'KAZAKHSTAN',
	'KENYA',
	'KOREA',
	'KUWAIT',
	'KYRGYZSTAN',
	'LAOS',
	'LATVIA',
	'LEBANON',
	'LESOTHO',
	'LIBERIA',
	'LIBYA',
	'LIECHTENSTEIN',
	'LITHUANIA',
	'LUXEMBOURG',
	'MACAO',
	'MADAGASCAR',
	'MALAWI',
	'MALAYSIA',
	'MALI',
	'MALTA',
	'MAURITANIA',
	'MAURITIUS',
	'MEXICO',
	'MOLDOVA',
	'MONACO',
	'MONTENEGRO',
	'MONTSERRAT',
	'MOROCCO',
	'MOZAMBIQUE',
	'MYANMAR',
	'NAMIBIA',
	'NEPAL',
	// The Netherlands itself. The list previously carried only the Netherlands
	// Antilles, wrapped over two lines, which dissolved in 2010 — Aruba and
	// Curaçao are listed in their own right.
	'NETHERLANDS',
	'NEW CALEDONIA',
	'NEW ZEALAND',
	'NICARAGUA',
	'NIGER',
	'NORWAY',
	'OMAN',
	'PAKISTAN',
	'PANAMA',
	'PAPUA NEW GUINEA',
	'PARAGUAY',
	'PERU',
	'PHILIPPINES',
	'POLAND',
	'PORTUGAL',
	'QATAR',
	'ROMANIA',
	'RUSSIA',
	'RWANDA',
	'ST. KITTS & NEVIS',
	'ST. LUCIA',
	// Too wide for the column at any size the page is set in.
	array( 'ST. VINCENT & THE', 'GRENADINES' ),
	// Renamed from Western Samoa in 1997, which moves it from W to S.
	'SAMOA',
	'SAN MARINO',
	'SAO TOME & PRINCIPE',
	'SAUDI ARABIA',
	'SENEGAL',
	'SERBIA',
	'SEYCHELLES',
	'SIERRA LEONE',
	'SINGAPORE',
	'SLOVAKIA',
	'SLOVENIA',
	'SOUTH AFRICA',
	'SPAIN',
	'SRI LANKA',
	'SUDAN',
	'SURINAME',
	'SWEDEN',
	'SWITZERLAND',
	'SYRIA',
	'TAIWAN',
	'TAJIKISTAN',
	'TANZANIA',
	'THAILAND',
	'TOGO',
	'TRINIDAD & TOBAGO',
	'TUNISIA',
	'TÜRKİYE',
	'TURKMENISTAN',
	'UGANDA',
	'UKRAINE',
	array( 'UNITED ARAB', 'EMIRATES' ),
	'UNITED KINGDOM',
	'UNITED STATES',
	'URUGUAY',
	'UZBEKISTAN',
	'VATICAN CITY',
	'VENEZUELA',
	'VIETNAM',
	'YEMEN',
	'ZAMBIA',
	'ZIMBABWE',
);

/*
 * Packed into the scan's four columns, filling each in turn. A multi-line entry
 * is never split across a column boundary, so a continuation line cannot end up
 * orphaned at the top of the next column.
 */
$states_lines = 0;

foreach ( $states as $states_entry ) {
	$states_lines += count( (array) $states_entry );
}

$states_per_column = (int) ceil( $states_lines / 4 );

/**
 * The four printed columns.
 *
 * @var array<int,string[]> $columns
 */
$columns       = array();
$states_column = array();

foreach ( $states as $states_entry ) {
	$states_entry_lines = (array) $states_entry;

	// The final column takes whatever is left, so it is never broken early.
	if (
		count( $columns ) < 3
		&& count( $states_column ) + count( $states_entry_lines ) > $states_per_column
	) {
		$columns[]     = $states_column;
		$states_column = array();
	}

	foreach ( $states_entry_lines as $states_line ) {
		$states_column[] = $states_line;
	}
}

$columns[] = $states_column;

$states_rows = max( array_map( 'count', $columns ) );
?>
<div class="states">

	<p class="states__intro">
		This permit is issued in accordance with the Geneva Convention on Road Traffic (1949) and/or the Vienna Convention on Road Traffic (1968).The validity of this permit is subject to the applicable Convention in force in each Contracting State.
	</p>

	<h2 class="states__title">LIST OF THE CONTRACTING STATES</h2>

	<table class="states__grid">
		<?php for ( $row = 0; $row < $states_rows; $row++ ) : ?>
			<tr>
				<?php foreach ( $columns as $column ) : ?>
					<td class="states__cell"><?php echo esc_html( $column[ $row ] ?? '' ); ?></td>
				<?php endforeach; ?>
			</tr>
		<?php endfor; ?>
	</table>

	<p class="states__footer">
		It is understood that this permit shall in no way affect the obligation of the holder to comply strictly with the laws and regulations relating to residence or to the exercise of a profession in force in each country through which he or she travels.
	</p>
</div>
