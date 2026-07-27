<?php
/**
 * Booklet page 2 — list of contracting states.
 *
 * A text rebuild of `assets/img/pages/final-booklet-IDPA_page-002.jpg`, the
 * heaviest scan in the booklet at ~850 KB.
 *
 * This page has its own layout rather than using the shared language layout, so
 * it lives in a numbered template. Column breaks reproduce the scan, including
 * the entries that wrap onto a second line.
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
 * Contracting states, in the scan's four printed columns.
 *
 * @var array<int,string[]> $columns
 */
$columns = array(
	array(
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
		'BOSNIA',
		'BOTSWANA',
		'BRAZIL',
		'BRUNEI',
		'BULGARIA',
		'BURKINA FASO',
		'CAMBODIA',
		'CAMEROON',
		'CANADA',
		'CAPE VERDE ISLANDS',
		'CAYMAN ISLANDS',
		'CENTRAL AFRICAN REP.',
		'CHAD',
		'CHILE',
		'COLOMBIA',
		'COMOROS',
		'CONGO',
		'COSTA RICA',
		'COTE D\'IVOIRE',
		'CROATIA',
		'CUBA',
		'CURACAO',
		'CYPRUS',
		'CZECH REP.',
		'DENMARK',
	),
	array(
		'DJIBOUTI',
		'DOMINICA',
		'DOMINICAN REP.',
		'ECUADOR',
		'EGYPT',
		'EL SALVADOR',
		'EQUATORIAL GUINEA',
		'ESTONIA',
		'FIJI',
		'FINLAND',
		'FRANCE',
		'FRENCH TERRITORIES',
		'FRENCH POLYNESIA',
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
		'HERZEGOVINA',
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
		'IVORY COAST',
		'JAMAICA',
		'JAPAN',
		'JERSEY',
		'JORDAN',
		'KAZAKHSTAN',
		'KENYA',
		'KOREA',
		'KUWAIT',
		'KYRGYSTAN',
	),
	array(
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
		'NETHERLANDS',
		'ANTILLES',
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
	),
	array(
		'ST. LUCIA',
		'ST. VINCENT & THE',
		'GRENADINES',
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
		'SWAZILAND',
		'SWEDEN',
		'SWITZERLAND',
		'SYRIA',
		'TAIWAN',
		'TAJIKSTAN',
		'TANZANIA',
		'THAILAND',
		'TOGO',
		'TRINIDAD & TOBAGO',
		'TUNISIA',
		'TÜRKİYE',
		'TURKMENISTAN',
		'UGANDA',
		'UKRAINE',
		'UNITED ARAB',
		'EMIRATES',
		'UNITED KINGDOM',
		'UNITED STATES',
		'URUGUAY',
		'UZBEKISTAN',
		'VATICAN CITY',
		'VENEZUELA',
		'VIETNAM',
		'WESTERN SAMOA',
		'YEMEN',
		'ZAMBIA',
		'ZIMBABWE',
	),
);

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
