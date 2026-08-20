<?php
/**
 * Booklet translation page content.
 *
 * Booklet pages 4-22 are one page repeated in nineteen languages: identical
 * layout, translated strings. The layout lives in
 * `templates/pages/language-page.php`; every string lives here, keyed by
 * booklet page number.
 *
 * Adding or correcting a language is pure data — no layout work. A page with no
 * entry here keeps rendering from its scan, so the booklet is always complete
 * and translations can land one at a time.
 *
 * Keys per entry:
 *   code         ISO language code, for reference.
 *   label        Language name, shown when no flag is drawn.
 *   folio        Page number as printed on the page.
 *   font         An mPDF font that covers the script:
 *                  dejavuserif    Latin, Cyrillic, Turkish, Lithuanian, Vietnamese
 *                  xbriyaz        Arabic
 *                  sun-exta       Chinese, Japanese
 *                  unbatang       Korean
 *                  abyssinicasil  Amharic
 *                  freeserif      Devanagari (Hindi)
 *                  garuda         Thai
 *                Register your own with the `idta_pdf_font_data` filter and
 *                name it here.
 *   weight       'bold' asks mPDF to thicken the face. The bundled CJK and
 *                Ethiopic fonts ship in one hairline weight, which prints far
 *                lighter than the scans; mPDF strokes the outline to fake the
 *                missing bold, which is what closes that gap.
 *   rtl          true mirrors the whole page (flag, letter column, stamp, divider).
 *   flag         Three colours for a vertical tricolour strip, or array() to
 *                print the language name instead. A three-stripe block is only
 *                an honest rendering of an actual vertical tricolour.
 *   flag_image   Path or URL to real flag artwork. Set, it replaces both the
 *                stripe block and the language name.
 *   lead_driver  Sentence beside holder field 1.
 *   lead_valid   Sentence beside holder field 5.
 *   holder       The five numbered holder fields.
 *   categories   The five vehicle-category rows, keyed A-E.
 *   notes        The two note columns (one full-width block when rtl).
 *   exclusion    title, intro, country, reason, seal, place, date, signature,
 *                footnote.
 *
 * Text was transcribed from the scanned pages and should be proof-read by a
 * native reader before print.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return array(

	// ---- Page 4: English (folio 1) ----
	4  => array(
		'code'        => 'en',
		'label'       => 'English',
		'folio'       => '1',
		// Not a vertical tricolour, so the layout names the language instead.
		'flag'        => array(),
		'lead_driver' => 'Particulars concerning the Driver:',
		'lead_valid'  => 'Vehicles for which permit is valid:',
		'holder'      => array(
			'Surname 1',
			'Other names 2',
			'Place of birth 3',
			'Date of birth 4',
			'Permanent place of residence 5',
		),
		'categories'  => array(
			'A' => 'Motorcycles, with or without a sidecar, invalid carriages and three-wheeled motor vehicles with an unloaded weight not exceeding 400 kg (900 lbs)',
			'B' => 'Motor vehicles used for the transport of passengers and comprising, in addition to the driver\'s seat, at most eight seats, or those used for the transport of goods and having permissible maximum weight not exceeding 3,500 kg (7,700 lbs.). Vehicles in this category may be coupled with a light trailer.',
			'C' => 'Motor vehicles used for the transport of goods and of which the permissible maximum weight exceeds 3,500 kg (7,700 lbs.). Vehicles in this category may be coupled with a light trailer.',
			'D' => 'Motor vehicles used for the transport of passengers and comprising, in addition to the driver\'s seat, more than eight seats. Vehicles in this category may be coupled with a light trailer.',
			'E' => 'Motor vehicles of categories B, C, or D, as authorized above, with other than a light trailer.',
		),
		'notes'       => array(
			'"Permissible maximum weight" of a vehicle means the weight of the vehicle and its maximum load when the vehicle is ready for the road. "Maximum load" means the weight of the load declared by the competent authority of the',
			'country of registrations of the vehicle. "Light Trailers" shall be those of a permissible maximum weight not exceeding 750 kg (1,650 lbs.).',
		),
		'exclusion'   => array(
			'title'     => 'EXCLUSION',
			'intro'     => 'Holder of this permit is deprived of the right to drive in',
			'country'   => '(country)',
			'reason'    => 'By reason of',
			'seal'      => 'Seal or<br>stamp of<br>authority',
			'place'     => 'Place',
			'date'      => 'Date',
			'signature' => 'Signature',
			'footnote'  => 'Should the above space be already filled, use any other space provided for "Exclusion".',
		),
	),

	// ---- Page 5: Arabic (folio 2), right-to-left ----
	5  => array(
		'code'        => 'ar',
		'label'       => 'العربية',
		'folio'       => '2',
		'font'        => 'xbriyaz',
		'rtl'         => true,
		'flag'        => array( '#006c35', '#006c35', '#006c35' ),
		'lead_driver' => 'بيانات تتعلق بالسائق:',
		'lead_valid'  => 'المركبات التي تكون الرخصة صالحة لقيادتها:',
		'holder'      => array(
			'اسم العائلة 1',
			'أسماء أخرى 2',
			'مكان الولادة 3',
			'تاريخ الميلاد 4',
			'محل إقامة الدائم 5',
		),
		'categories'  => array(
			'A' => 'الدراجات النارية، مع أو بدون صندوق جانبي (سايدكار)، ومركبات ذوي الإعاقة، والمركبات الآلية ذات الثلاث عجلات التي لا يزيد وزنها الفارغ على 400 كغ',
			'B' => 'المركبات الآلية المستخدمة لنقل الركاب، والتي تضم — إضافة إلى مقعد السائق — ما لا يزيد على ثمانية مقاعد، أو المركبات المستخدمة لنقل البضائع والتي لا يتجاوز وزنها الأقصى المسموح به 3,500 كغ. ويجوز في هذه الفئة اقتران المركبة بمقطورة خفيفة',
			'C' => 'المركبات الآلية المستخدمة لنقل البضائع والتي يتجاوز وزنها الأقصى المسموح به 3,500 كغ. ويجوز في هذه الفئة اقتران المركبة بمقطورة خفيفة',
			'D' => 'المركبات الآلية المستخدمة لنقل الركاب، والتي تضم — إضافة إلى مقعد السائق — أكثر من ثمانية مقاعد. ويجوز في هذه الفئة اقتران المركبة بمقطورة خفيفة',
			'E' => 'المركبات الآلية من الفئات B أو C أو D، كما هو مصرح به أعلاه، مقترنة بمقطورة غير المقطورة الخفيفة',
		),
		'notes'       => array(
			'يقصد بعبارة "الوزن الأقصى المسموح به" للمركبة وزن المركبة مع حمولتها القصوى عندما تكون جاهزة للسير على الطريق. ويقصد بعبارة "الحمولة القصوى" وزن الحمولة المعلن من قبل السلطة المختصة في بلد تسجيل المركبة.',
			'وتعد "المقطورات الخفيفة" هي التي لا يزيد وزنها الأقصى المسموح به على 750 كغ.',
		),
		'exclusion'   => array(
			'title'     => '(المنع / الاستبعاد)',
			'intro'     => 'يحرم حامل هذه الرخصة من حق القيادة في (الدولة)',
			'country'   => '',
			'reason'    => 'بسبب',
			'seal'      => 'الختم أو طابع<br>السلطة<br>المختصة',
			'place'     => 'المكان',
			'date'      => 'التاريخ',
			'signature' => 'التوقيع',
			'footnote'  => 'إذا كانت المساحة أعلاه ممتلئة بالفعل، يرجى استخدام أي مساحة أخرى مخصصة لـ المنع / الاستبعاد.',
		),
	),

	// ---- Page 6: French (folio 3) ----
	6  => array(
		'code'        => 'fr',
		'label'       => 'Français',
		'folio'       => '3',
		'flag'        => array( '#0055a4', '#ffffff', '#ef4135' ),
		'lead_driver' => 'Renseignements concernant le conducteur :',
		'lead_valid'  => 'Véhicules pour lesquels le permis est valable :',
		'holder'      => array(
			'Nom 1',
			'Autres noms 2',
			'Lieu de naissance 3',
			'Date de naissance 4',
			'Lieu de résidence permanente 5',
		),
		'categories'  => array(
			'A' => 'Motocycles, avec ou sans side-car, voitures pour invalides et véhicules automobiles à trois roues dont le poids à vide n’excède pas 400 kg (900 lb).',
			'B' => 'Véhicules automobiles affectés au transport de personnes et comprenant, outre le siège du conducteur, au maximum huit places, ou véhicules affectés au transport de marchandises et dont le poids total autorisé n’excède pas 3 500 kg (7 700 lb). Les véhicules de cette catégorie peuvent être attelés d’une remorque légère.',
			'C' => 'Véhicules automobiles affectés au transport de marchandises et dont le poids total autorisé dépasse 3 500 kg (7 700 lb). Les véhicules de cette catégorie peuvent être attelés d’une remorque légère.',
			'D' => 'Véhicules automobiles affectés au transport de personnes et comprenant, outre le siège du conducteur, plus de huit places. Les véhicules de cette catégorie peuvent être attelés d’une remorque légère.',
			'E' => 'Véhicules automobiles des catégories B, C ou D, tels qu’autorisés ci-dessus, attelés d’une remorque autre qu’une remorque légère.',
		),
		'notes'       => array(
			'Le «&nbsp;poids total autorisé&nbsp;» d’un véhicule désigne le poids du véhicule et de sa charge maximale lorsque le véhicule est prêt à circuler sur route. La «&nbsp;charge maximale&nbsp;» désigne le poids de la charge déclaré par l’autorité compétente du',
			'pays d’immatriculation du véhicule. Les «&nbsp;remorques légères&nbsp;» sont celles dont le poids total autorisé n’excède pas 750 kg (1 650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'EXCLUSION',
			'intro'     => 'Le titulaire du présent permis est privé du droit de conduire dans',
			'country'   => '(pays)',
			'reason'    => 'Pour le motif de',
			'seal'      => 'Cachet ou<br>timbre de<br>l’autorité',
			'place'     => 'Lieu',
			'date'      => 'Date',
			'signature' => 'Signature',
			'footnote'  => 'Si l’espace ci-dessus est déjà rempli, utiliser tout autre espace prévu pour «&nbsp;Exclusion&nbsp;».',
		),
	),
	// ---- Page 7: Russian (folio 4) ----
	7  => array(
		'code'        => 'ru',
		'label'       => 'Русский',
		'folio'       => '4',
		'flag'        => array(),
		'lead_driver' => 'Сведения о водителе:',
		'lead_valid'  => 'Транспортные средства, на управление которыми действует разрешение:',
		'holder'      => array(
			'Фамилия 1',
			'Другие имена 2',
			'Место рождения 3',
			'Дата рождения 4',
			'Постоянное место жительства 5',
		),
		'categories'  => array(
			'A' => 'Мотоциклы, с коляской или без нее, инвалидные коляски и трехколесные моторные транспортные средства с снаряженной массой, не превышающей 400 кг (900 фунтов).',
			'B' => 'Моторные транспортные средства, предназначенные для перевозки пассажиров и имеющие, помимо места водителя, не более восьми мест, либо предназначенные для перевозки грузов и с допустимой максимальной массой не более 3 500 кг (7 700 фунтов). Транспортные средства данной категории могут быть сцеплены с легким прицепом.',
			'C' => 'Моторные транспортные средства, предназначенные для перевозки грузов, допустимая максимальная масса которых превышает 3 500 кг (7 700 фунтов). Транспортные средства данной категории могут быть сцеплены с легким прицепом.',
			'D' => 'Моторные транспортные средства, предназначенные для перевозки пассажиров и имеющие, помимо места водителя, более восьми мест. Транспортные средства данной категории могут быть сцеплены с легким прицепом.',
			'E' => 'Моторные транспортные средства категорий B, C или D, указанных выше, сцепленные с прицепом, отличным от легкого прицепа.',
		),
		'notes'       => array(
			'«Допустимая максимальная масса» транспортного средства означает массу транспортного средства и его максимальной нагрузки, когда транспортное средство готово к движению.',
			'«Максимальная нагрузка» означает массу груза, заявленную компетентным органом страны регистрации транспортного средства. «Легкие прицепы» — это прицепы с допустимой максимальной массой не более 750 кг (1 650 фунтов).',
		),
		'exclusion'   => array(
			'title'     => 'ИСКЛЮЧЕНИЕ',
			'intro'     => 'Владелец данного разрешения лишается права управления транспортными средствами в',
			'country'   => '(страна)',
			'reason'    => 'По причине',
			'seal'      => 'Печать<br>или штамп<br>органа',
			'place'     => 'Место',
			'date'      => 'Дата',
			'signature' => 'Подпись',
			'footnote'  => 'Если указанное выше место уже заполнено, используйте любое другое место, предназначенное для «Исключение».',
		),
	),

	// ---- Page 9: Spanish (folio 6) ----
	9  => array(
		'code'        => 'es',
		'label'       => 'Español',
		'folio'       => '6',
		'flag'        => array(),
		'lead_driver' => 'Datos relativos al conductor:',
		'lead_valid'  => 'Vehículos para los cuales es válido el permiso:',
		'holder'      => array(
			'Apellido 1',
			'Otros nombres 2',
			'Lugar de nacimiento 3',
			'Fecha de nacimiento 4',
			'Lugar de residencia permanente 5',
		),
		'categories'  => array(
			'A' => 'Motocicletas, con o sin sidecar, vehículos para inválidos y vehículos automotores de tres ruedas cuyo peso en vacío no exceda de 400 kg (900 lb).',
			'B' => 'Vehículos automotores destinados al transporte de pasajeros y que comprendan, además del asiento del conductor, como máximo ocho plazas, o destinados al transporte de mercancías y cuyo peso máximo autorizado no exceda de 3.500 kg (7.700 lb). Los vehículos de esta categoría pueden ir acoplados a un remolque ligero.',
			'C' => 'Vehículos automotores destinados al transporte de mercancías cuyo peso máximo autorizado exceda de 3.500 kg (7.700 lb). Los vehículos de esta categoría pueden ir acoplados a un remolque ligero.',
			'D' => 'Vehículos automotores destinados al transporte de pasajeros y que comprendan, además del asiento del conductor, más de ocho plazas. Los vehículos de esta categoría pueden ir acoplados a un remolque ligero.',
			'E' => 'Vehículos automotores de las categorías B, C o D, autorizados anteriormente, acoplados a un remolque distinto de un remolque ligero.',
		),
		'notes'       => array(
			'El “peso máximo autorizado” de un vehículo significa el peso del vehículo y de su carga máxima cuando está listo para circular. La “carga máxima” significa el peso de la carga declarado por la autoridad competente del país de matriculación del vehículo.',
			'Los “remolques ligeros” son aquellos cuyo peso máximo autorizado no excede de 750 kg (1.650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'EXCLUSIÓN',
			'intro'     => 'El titular de este permiso queda privado del derecho a conducir en',
			'country'   => '(país)',
			'reason'    => 'Por razón de',
			'seal'      => 'Sello o<br>timbre de<br>la autoridad',
			'place'     => 'Lugar',
			'date'      => 'Fecha',
			'signature' => 'Firma',
			'footnote'  => 'Si el espacio anterior ya está lleno, utilícese cualquier otro espacio previsto para «Exclusión».',
		),
	),

	// ---- Page 10: Bulgarian (folio 7) ----
	10 => array(
		'code'        => 'bg',
		'label'       => 'Български',
		'folio'       => '7',
		'flag'        => array(),
		'lead_driver' => 'Данни за водача:',
		'lead_valid'  => 'Превозни средства, за които разрешението е валидно:',
		'holder'      => array(
			'Фамилия 1',
			'Други имена 2',
			'Място на раждане 3',
			'Дата на раждане 4',
			'Постоянно местоживеене 5',
		),
		'categories'  => array(
			'A' => 'Мотоциклети, със или без кош, превозни средства за инвалиди и триколесни моторни превозни средства с маса без товар, ненадвишаваща 400 kg (900 lb).',
			'B' => 'Моторни превозни средства за превоз на пътници, включително освен мястото на водача най-много осем места, или за превоз на товари с допустима максимална маса, ненадвишаваща 3 500 kg (7 700 lb). Превозните средства от тази категория могат да бъдат съчленени с леко ремарке.',
			'C' => 'Моторни превозни средства за превоз на товари с допустима максимална маса, надвишаваща 3 500 kg (7 700 lb). Превозните средства от тази категория могат да бъдат съчленени с леко ремарке.',
			'D' => 'Моторни превозни средства за превоз на пътници, включително освен мястото на водача повече от осем места. Превозните средства от тази категория могат да бъдат съчленени с леко ремарке.',
			'E' => 'Моторни превозни средства от категории B, C или D, както е разрешено по-горе, с ремарке, различно от леко ремарке.',
		),
		'notes'       => array(
			'„Допустима максимална маса“ на превозно средство означава масата на превозното средство и неговия максимален товар, когато е готово за движение по пътя.',
			'„Максимален товар“ означава масата на товара, декларирана от компетентния орган на държавата по регистрация на превозното средство. „Леки ремаркета“ са тези с допустима максимална маса, ненадвишаваща 750 kg (1 650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'ИЗКЛЮЧЕНИЕ',
			'intro'     => 'Притежателят на настоящото разрешение е лишен от правото да управлява в',
			'country'   => '(държава)',
			'reason'    => 'Поради причина',
			'seal'      => 'Печат или<br>щемпел<br>на органа',
			'place'     => 'Място',
			'date'      => 'Дата',
			'signature' => 'Подпис',
			'footnote'  => 'Ако горното място вече е запълнено, използвайте всяко друго място, предвидено за „Изключение“.',
		),
	),

	// ---- Page 11: Italian (folio 8) ----
	11 => array(
		'code'        => 'it',
		'label'       => 'Italiano',
		'folio'       => '8',
		'flag'        => array( '#008c45', '#f4f5f0', '#cd212a' ),
		'lead_driver' => 'Dati relativi al conducente:',
		'lead_valid'  => 'Veicoli per i quali il permesso è valido:',
		'holder'      => array(
			'Cognome 1',
			'Altri nomi 2',
			'Luogo di nascita 3',
			'Data di nascita 4',
			'Luogo di residenza permanente 5',
		),
		'categories'  => array(
			'A' => 'Motocicli, con o senza sidecar, veicoli per invalidi e veicoli a motore a tre ruote con peso a vuoto non superiore a 400 kg (900 lb).',
			'B' => 'Veicoli a motore destinati al trasporto di persone e comprendenti, oltre al posto del conducente, al massimo otto posti, oppure destinati al trasporto di merci e con massa massima ammissibile non superiore a 3.500 kg (7.700 lb). I veicoli di questa categoria possono essere accoppiati a un rimorchio leggero.',
			'C' => 'Veicoli a motore destinati al trasporto di merci la cui massa massima ammissibile supera 3.500 kg (7.700 lb). I veicoli di questa categoria possono essere accoppiati a un rimorchio leggero.',
			'D' => 'Veicoli a motore destinati al trasporto di persone e comprendenti, oltre al posto del conducente, più di otto posti. I veicoli di questa categoria possono essere accoppiati a un rimorchio leggero.',
			'E' => 'Veicoli a motore delle categorie B, C o D, come autorizzato sopra, accoppiati a un rimorchio diverso da un rimorchio leggero.',
		),
		'notes'       => array(
			'La “massa massima ammissibile” di un veicolo indica la massa del veicolo e del suo carico massimo quando è pronto per la circolazione stradale.',
			'Il “carico massimo” indica la massa del carico dichiarata dall’autorità competente del paese di immatricolazione del veicolo. I “rimorchi leggeri” sono quelli la cui massa massima ammissibile non supera 750 kg (1.650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'ESCLUSIONE',
			'intro'     => 'Il titolare del presente permesso è privato del diritto di guidare in',
			'country'   => '(paese)',
			'reason'    => 'Per il motivo di',
			'seal'      => 'Timbro o<br>sigillo<br>dell’autorità',
			'place'     => 'Luogo',
			'date'      => 'Data',
			'signature' => 'Firma',
			'footnote'  => 'Se lo spazio sopra indicato è già compilato, utilizzare qualsiasi altro spazio previsto per “Esclusione”.',
		),
	),

	// ---- Page 12: Lithuanian (folio 9) ----
	12 => array(
		'code'        => 'lt',
		'label'       => 'Lietuvių',
		'folio'       => '9',
		'flag'        => array(),
		'lead_driver' => 'Duomenys apie vairuotoją:',
		'lead_valid'  => 'Transporto priemonės, kurioms galioja leidimas:',
		'holder'      => array(
			'Pavardė 1',
			'Kiti vardai 2',
			'Gimimo vieta 3',
			'Gimimo data 4',
			'Nuolatinė gyvenamoji vieta 5',
		),
		'categories'  => array(
			'A' => 'Motociklai su arba be šoninės priekabos, neįgaliųjų transporto priemonės ir triračiai motoriniai automobiliai, kurių neapkrautas svoris neviršija 400 kg (900 lb).',
			'B' => 'Motorinės transporto priemonės, skirtos keleiviams vežti ir turinčios, be vairuotojo sėdynės, ne daugiau kaip aštuonias vietas, arba skirtos kroviniams vežti, kurių leidžiamas maksimalus svoris neviršija 3 500 kg (7 700 lb). Šios kategorijos transporto priemonės gali būti sujungtos su lengvąja priekaba.',
			'C' => 'Motorinės transporto priemonės, skirtos kroviniams vežti, kurių leidžiamas maksimalus svoris viršija 3 500 kg (7 700 lb). Šios kategorijos transporto priemonės gali būti sujungtos su lengvąja priekaba.',
			'D' => 'Motorinės transporto priemonės, skirtos keleiviams vežti ir turinčios, be vairuotojo sėdynės, daugiau nei aštuonias vietas. Šios kategorijos transporto priemonės gali būti sujungtos su lengvąja priekaba.',
			'E' => 'Motorinės transporto priemonės, priklausančios B, C arba D kategorijoms, kaip nurodyta aukščiau, sujungtos su kita nei lengvąja priekaba.',
		),
		'notes'       => array(
			'Transporto priemonės „leidžiamas maksimalus svoris“ reiškia transporto priemonės ir jos maksimalaus krovinio svorį, kai ji paruošta važiuoti keliu.',
			'„Maksimalus krovinys“ reiškia krovinio svorį, deklaruotą kompetentingos transporto priemonės registracijos šalies institucijos. „Lengvosios priekabos“ – tai priekabos, kurių leidžiamas maksimalus svoris neviršija 750 kg (1 650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'IŠIMTIS',
			'intro'     => 'Šio leidimo turėtojas netenka teisės vairuoti',
			'country'   => '(šalyje)',
			'reason'    => 'Dėl priežasties',
			'seal'      => 'Institucijos<br>antspaudas<br>arba spaudas',
			'place'     => 'Vieta',
			'date'      => 'Data',
			'signature' => 'Parašas',
			'footnote'  => 'Jei aukščiau nurodyta vieta jau užpildyta, naudokite bet kurią kitą vietą, skirtą „Išimčiai“.',
		),
	),

	// ---- Page 14: Turkish (folio 11) ----
	14 => array(
		'code'        => 'tr',
		'label'       => 'Türkçe',
		'folio'       => '11',
		'flag'        => array(),
		'lead_driver' => 'Sürücüye ilişkin bilgiler:',
		'lead_valid'  => 'İznin geçerli olduğu taşıtlar:',
		'holder'      => array(
			'Soyadı 1',
			'Diğer adlar 2',
			'Doğum yeri 3',
			'Doğum tarihi 4',
			'Daimi ikamet yeri 5',
		),
		'categories'  => array(
			'A' => 'Yan sepetli veya sepetsiz motosikletler, engelli araçları ve boş ağırlığı 400 kg’ı (900 lb) aşmayan üç tekerlekli motorlu taşıtlar.',
			'B' => 'Sürücü koltuğu dışında en fazla sekiz koltuğu bulunan yolcu taşımaya mahsus motorlu taşıtlar veya azami izin verilen ağırlığı 3.500 kg’ı (7.700 lb) aşmayan yük taşımaya mahsus motorlu taşıtlar. Bu kategoriye giren taşıtlar hafif römork ile çekilebilir.',
			'C' => 'Azami izin verilen ağırlığı 3.500 kg’ı (7.700 lb) aşan yük taşımaya mahsus motorlu taşıtlar. Bu kategoriye giren taşıtlar hafif römork ile çekilebilir.',
			'D' => 'Sürücü koltuğu dışında sekizden fazla koltuğu bulunan yolcu taşımaya mahsus motorlu taşıtlar. Bu kategoriye giren taşıtlar hafif römork ile çekilebilir.',
			'E' => 'Yukarıda izin verilen B, C veya D kategorilerindeki motorlu taşıtlar, hafif römork dışında bir römork ile.',
		),
		'notes'       => array(
			'Bir taşıtın “azami izin verilen ağırlığı”, taşıtın yola çıkmaya hazır durumdaki ağırlığı ile azami yükünün toplamını ifade eder.',
			'“Azami yük”, taşıtın tescil edildiği ülkenin yetkili makamı tarafından beyan edilen yük ağırlığını ifade eder. “Hafif römorklar”, azami izin verilen ağırlığı 750 kg’ı (1.650 lb) aşmayan römorklardır.',
		),
		'exclusion'   => array(
			'title'     => 'HARİÇ TUTMA',
			'intro'     => 'Bu belgenin sahibi, (ülke) içinde araç kullanma hakkından mahrum edilmiştir',
			'country'   => '',
			'reason'    => 'Sebebi',
			'seal'      => 'Yetkili<br>makamın mührü<br>veya kaşesi',
			'place'     => 'Yer',
			'date'      => 'Tarih',
			'signature' => 'İmza',
			'footnote'  => 'Yukarıdaki alan doluysa, “Hariç Tutma” için ayrılmış başka bir alan kullanılır.',
		),
	),

	// ---- Page 15: Malay (folio 12) ----
	15 => array(
		'code'        => 'ms',
		'label'       => 'Bahasa Melayu',
		'folio'       => '12',
		'flag'        => array(),
		'lead_driver' => 'Maklumat mengenai pemandu:',
		'lead_valid'  => 'Kenderaan yang permit ini sah untuknya:',
		'holder'      => array(
			'Nama keluarga 1',
			'Nama lain 2',
			'Tempat lahir 3',
			'Tarikh lahir 4',
			'Tempat kediaman tetap 5',
		),
		'categories'  => array(
			'A' => 'Motosikal, dengan atau tanpa sidecar, kenderaan untuk orang kurang upaya dan kenderaan bermotor tiga roda dengan berat tanpa muatan tidak melebihi 400 kg (900 lb).',
			'B' => 'Kenderaan bermotor yang digunakan untuk pengangkutan penumpang dan mempunyai, selain tempat duduk pemandu, tidak lebih daripada lapan tempat duduk, atau digunakan untuk pengangkutan barangan dan mempunyai berat maksimum yang dibenarkan tidak melebihi 3,500 kg (7,700 lb). Kenderaan dalam kategori ini boleh ditarik dengan treler ringan.',
			'C' => 'Kenderaan bermotor yang digunakan untuk pengangkutan barangan dan yang berat maksimum dibenarkan melebihi 3,500 kg (7,700 lb). Kenderaan dalam kategori ini boleh ditarik dengan treler ringan.',
			'D' => 'Kenderaan bermotor yang digunakan untuk pengangkutan penumpang dan mempunyai, selain tempat duduk pemandu, lebih daripada lapan tempat duduk. Kenderaan dalam kategori ini boleh ditarik dengan treler ringan.',
			'E' => 'Kenderaan bermotor kategori B, C atau D seperti yang dibenarkan di atas, dengan treler selain treler ringan.',
		),
		'notes'       => array(
			'“Berat maksimum dibenarkan” bagi sesebuah kenderaan bermaksud berat kenderaan dan muatan maksimumnya apabila sedia untuk digunakan di jalan raya.',
			'“Muatan maksimum” bermaksud berat muatan yang diisytiharkan oleh pihak berkuasa kompeten negara pendaftaran kenderaan. “Treler ringan” ialah treler yang berat maksimum dibenarkan tidak melebihi 750 kg (1,650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'PENGECUALIAN',
			'intro'     => 'Pemegang permit ini dilucutkan hak untuk memandu di',
			'country'   => '(negara)',
			'reason'    => 'Atas sebab',
			'seal'      => 'Cop atau<br>cap pihak<br>berkuasa',
			'place'     => 'Tempat',
			'date'      => 'Tarikh',
			'signature' => 'Tandatangan',
			'footnote'  => 'Jika ruang di atas telah diisi, gunakan mana-mana ruang lain yang disediakan untuk “Pengecualian”.',
		),
	),

	// ---- Page 17: German (folio 14) ----
	17 => array(
		'code'        => 'de',
		'label'       => 'Deutsch',
		'folio'       => '14',
		'flag'        => array(),
		'lead_driver' => 'Angaben zum Fahrer:',
		'lead_valid'  => 'Fahrzeuge, für die der Führerschein gültig ist:',
		'holder'      => array(
			'Familienname 1',
			'Weitere Namen 2',
			'Geburtsort 3',
			'Geburtsdatum 4',
			'Ständiger Wohnsitz 5',
		),
		'categories'  => array(
			'A' => 'Krafträder mit oder ohne Beiwagen, Fahrzeuge für Behinderte und dreirädrige Kraftfahrzeuge mit einem Leergewicht von höchstens 400 kg (900 lb).',
			'B' => 'Kraftfahrzeuge zur Beförderung von Personen mit neben dem Fahrersitz höchstens acht Sitzplätzen oder zur Beförderung von Gütern mit einem zulässigen Gesamtgewicht von höchstens 3.500 kg (7.700 lb). Fahrzeuge dieser Kategorie dürfen mit einem leichten Anhänger gekuppelt werden.',
			'C' => 'Kraftfahrzeuge zur Beförderung von Gütern, deren zulässiges Gesamtgewicht 3.500 kg (7.700 lb) übersteigt. Fahrzeuge dieser Kategorie dürfen mit einem leichten Anhänger gekuppelt werden.',
			'D' => 'Kraftfahrzeuge zur Beförderung von Personen mit neben dem Fahrersitz mehr als acht Sitzplätzen. Fahrzeuge dieser Kategorie dürfen mit einem leichten Anhänger gekuppelt werden.',
			'E' => 'Kraftfahrzeuge der Kategorien B, C oder D, wie oben zugelassen, mit einem anderen als einem leichten Anhänger.',
		),
		'notes'       => array(
			'Das „zulässige Gesamtgewicht“ eines Fahrzeugs bezeichnet das Gewicht des Fahrzeugs und seiner maximalen Ladung, wenn es für den Straßenverkehr bereit ist.',
			'Die „maximale Ladung“ bezeichnet das Gewicht der Ladung, das von der zuständigen Behörde des Zulassungsstaates des Fahrzeugs angegeben wird. „Leichte Anhänger“ sind solche mit einem zulässigen Gesamtgewicht von höchstens 750 kg (1.650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'AUSSCHLUSS',
			'intro'     => 'Der Inhaber dieses Führerscheins ist des Rechts beraubt, in (Land) Kraftfahrzeuge zu führen',
			'country'   => '',
			'reason'    => 'Aus folgendem Grund',
			'seal'      => 'Siegel oder<br>Stempel der<br>Behörde',
			'place'     => 'Ort',
			'date'      => 'Datum',
			'signature' => 'Unterschrift',
			'footnote'  => 'Ist der obenstehende Raum bereits ausgefüllt, ist jeder andere für den „Ausschluss“ vorgesehene Raum zu verwenden.',
		),
	),

	// ---- Page 18: Portuguese (folio 15) ----
	18 => array(
		'code'        => 'pt',
		'label'       => 'Português',
		'folio'       => '15',
		'flag'        => array(),
		'lead_driver' => 'Dados relativos ao condutor:',
		'lead_valid'  => 'Veículos para os quais a licença é válida:',
		'holder'      => array(
			'Sobrenome 1',
			'Outros nomes 2',
			'Local de nascimento 3',
			'Data de nascimento 4',
			'Local de residência permanente 5',
		),
		'categories'  => array(
			'A' => 'Motocicletas, com ou sem sidecar, veículos para pessoas com deficiência e veículos automotores de três rodas com peso sem carga não superior a 400 kg (900 lb).',
			'B' => 'Veículos automotores destinados ao transporte de passageiros e que compreendam, além do assento do condutor, no máximo oito lugares, ou destinados ao transporte de mercadorias e com peso máximo permitido não superior a 3.500 kg (7.700 lb). Os veículos desta categoria podem ser acoplados a um reboque leve.',
			'C' => 'Veículos automotores destinados ao transporte de mercadorias cujo peso máximo permitido exceda 3.500 kg (7.700 lb). Os veículos desta categoria podem ser acoplados a um reboque leve.',
			'D' => 'Veículos automotores destinados ao transporte de passageiros e que compreendam, além do assento do condutor, mais de oito lugares. Os veículos desta categoria podem ser acoplados a um reboque leve.',
			'E' => 'Veículos automotores das categorias B, C ou D, conforme autorizado acima, com reboque diferente de reboque leve.',
		),
		'notes'       => array(
			'O “peso máximo permitido” de um veículo significa o peso do veículo e da sua carga máxima quando está pronto para circular.',
			'A “carga máxima” significa o peso da carga declarado pela autoridade competente do país de registro do veículo. “Reboques leves” são aqueles cujo peso máximo permitido não excede 750 kg (1.650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'EXCLUSÃO',
			'intro'     => 'O titular desta licença é privado do direito de conduzir em',
			'country'   => '(país)',
			'reason'    => 'Pelo motivo de',
			'seal'      => 'Selo ou<br>carimbo da<br>autoridade',
			'place'     => 'Local',
			'date'      => 'Data',
			'signature' => 'Assinatura',
			'footnote'  => 'Se o espaço acima já estiver preenchido, utilize qualquer outro espaço previsto para “Exclusão”.',
		),
	),

	// ---- Page 20: Vietnamese (folio 17) ----
	20 => array(
		'code'        => 'vi',
		'label'       => 'Tiếng Việt',
		'folio'       => '17',
		'flag'        => array(),
		'lead_driver' => 'Thông tin liên quan đến người lái xe:',
		'lead_valid'  => 'Phương tiện mà giấy phép có hiệu lực:',
		'holder'      => array(
			'Họ 1',
			'Tên khác 2',
			'Nơi sinh 3',
			'Ngày sinh 4',
			'Nơi cư trú thường xuyên 5',
		),
		'categories'  => array(
			'A' => 'Xe mô tô, có hoặc không có thùng xe phụ, xe dành cho người khuyết tật và xe cơ giới ba bánh có trọng lượng không tải không vượt quá 400 kg (900 lb).',
			'B' => 'Xe cơ giới dùng để vận chuyển hành khách và có, ngoài chỗ ngồi của người lái, tối đa tám chỗ ngồi, hoặc dùng để vận chuyển hàng hóa và có khối lượng toàn bộ cho phép không vượt quá 3.500 kg (7.700 lb). Xe thuộc loại này có thể kéo theo rơ-moóc nhẹ.',
			'C' => 'Xe cơ giới dùng để vận chuyển hàng hóa có khối lượng toàn bộ cho phép vượt quá 3.500 kg (7.700 lb). Xe thuộc loại này có thể kéo theo rơ-moóc nhẹ.',
			'D' => 'Xe cơ giới dùng để vận chuyển hành khách và có, ngoài chỗ ngồi của người lái, hơn tám chỗ ngồi. Xe thuộc loại này có thể kéo theo rơ-moóc nhẹ.',
			'E' => 'Xe cơ giới thuộc các loại B, C hoặc D như được phép ở trên, kéo theo rơ-moóc không phải là rơ-moóc nhẹ.',
		),
		'notes'       => array(
			'“Khối lượng toàn bộ cho phép” của một phương tiện là trọng lượng của phương tiện và tải trọng tối đa của nó khi sẵn sàng lưu thông trên đường.',
			'“Tải trọng tối đa” là trọng lượng của tải trọng do cơ quan có thẩm quyền của quốc gia đăng ký phương tiện khai báo. “Rơ-moóc nhẹ” là rơ-moóc có khối lượng toàn bộ cho phép không vượt quá 750 kg (1.650 lb).',
		),
		'exclusion'   => array(
			'title'     => 'LOẠI TRỪ',
			'intro'     => 'Người giữ giấy phép này bị tước quyền lái xe tại',
			'country'   => '(quốc gia)',
			'reason'    => 'Vì lý do',
			'seal'      => 'Dấu hoặc<br>con dấu của<br>cơ quan',
			'place'     => 'Địa điểm',
			'date'      => 'Ngày',
			'signature' => 'Chữ ký',
			'footnote'  => 'Nếu chỗ trống trên đã được điền, hãy sử dụng bất kỳ chỗ trống nào khác dành cho “Loại trừ”.',
		),
	),

	// ---- Page 8: Chinese (folio 5) ----
	8  => array(
		'code'        => 'zh',
		'label'       => '中文',
		'folio'       => '5',
		'font'        => 'sun-exta',
		'weight'      => 'bold',
		'flag'        => array(),
		'lead_driver' => '驾驶员资料：',
		'lead_valid'  => '本许可证适用的车辆类别：',
		'holder'      => array(
			'姓 1',
			'其他姓名 2',
			'出生地 3',
			'出生日期 4',
			'永久居住地 5',
		),
		'categories'  => array(
			'A' => '摩托车（带或不带边车）、残疾人车辆以及空载重量不超过 400 公斤（900 磅）的三轮机动车。',
			'B' => '用于载客的机动车，除驾驶员座位外，最多不超过八个座位；或用于载货且允许的最大总质量不超过 3,500 公斤（7,700 磅）的机动车。本类别车辆可牵引轻型拖车。',
			'C' => '用于载货且允许的最大总质量超过 3,500 公斤（7,700 磅）的机动车。本类别车辆可牵引轻型拖车。',
			'D' => '用于载客的机动车，除驾驶员座位外，超过八个座位。本类别车辆可牵引轻型拖车。',
			'E' => '上述 B、C 或 D 类机动车，牵引非轻型拖车的车辆。',
		),
		'notes'       => array(
			'车辆的“允许最大总质量”是指车辆在可上路行驶状态下的车辆重量及其最大载荷。“最大载荷”是指由车辆登记国主管机关申报的载荷重量。',
			'“轻型拖车”是指允许最大总质量不超过 750 公斤（1,650 磅）的拖车。',
		),
		'exclusion'   => array(
			'title'     => '除外',
			'intro'     => '本许可证持有人被剥夺在（国家）驾驶的权利',
			'country'   => '',
			'reason'    => '原因',
			'seal'      => '主管机<br>关印章<br>或盖章',
			'place'     => '地点',
			'date'      => '日期',
			'signature' => '签名',
			'footnote'  => '如上述空间已填写完毕，请使用任何其他标注为“除外”的空间。',
		),
	),

	// ---- Page 19: Japanese (folio 16) ----
	19 => array(
		'code'        => 'ja',
		'label'       => '日本語',
		'folio'       => '16',
		'font'        => 'sun-exta',
		'weight'      => 'bold',
		'flag'        => array(),
		'lead_driver' => '運転者に関する事項:',
		'lead_valid'  => '本許可証が有効な車両:',
		'holder'      => array(
			'姓 1',
			'その他の氏名 2',
			'出生地 3',
			'生年月日 4',
			'恒久的居住地 5',
		),
		'categories'  => array(
			'A' => 'サイドカーの有無を問わないオートバイ、身体障害者用車両、および空車重量が 400 kg（900 lb）を超えない三輪自動車。',
			'B' => '運転者席のほかに最大8席を有する旅客輸送用自動車、または許容最大重量が 3,500 kg（7,700 lb）を超えない貨物輸送用自動車。本区分の車両は軽トレーラーを連結することができる。',
			'C' => '許容最大重量が 3,500 kg（7,700 lb）を超える貨物輸送用自動車。本区分の車両は軽トレーラーを連結することができる。',
			'D' => '運転者席のほかに8席を超える座席を有する旅客輸送用自動車。本区分の車両は軽トレーラーを連結することができる。',
			'E' => '上記で許可されたB、CまたはD区分の自動車で、軽トレーラー以外のトレーラーを連結したもの。',
		),
		'notes'       => array(
			'車両の「許容最大重量」とは、走行準備が整った状態における車両およびその最大積載量の重量をいう。',
			'「最大積載量」とは、車両登録国の管轄当局により申告された積載量の重量をいう。「軽トレーラー」とは、許容最大重量が 750 kg（1,650 lb）を超えないトレーラーをいう。',
		),
		'exclusion'   => array(
			'title'     => '除外',
			'intro'     => '本許可証の所持者は、（国）において運転する権利を剥奪される',
			'country'   => '',
			'reason'    => '理由',
			'seal'      => '当局の<br>印章',
			'place'     => '場所',
			'date'      => '日付',
			'signature' => '署名',
			'footnote'  => '上記の欄が既に記入されている場合は、「除外」のために用意された他の欄を使用すること。',
		),
	),

	// ---- Page 13: Amharic (folio 10) ----
	13 => array(
		'code'        => 'am',
		'label'       => 'አማርኛ',
		'folio'       => '10',
		// FreeSerif reads as a cleaner book face for Ethiopic than Abyssinica
		// SIL's heavier, rounder default; freeserif's bold variant (unlike its
		// regular) has no Ethiopic glyphs at all, so this page must stay at the
		// default normal weight rather than asking for bold like the CJK pages.
		'font'        => 'freeserif',
		'flag'        => array(),
		'lead_driver' => 'የአሽከርካሪ መረጃ:',
		'lead_valid'  => 'ፈቃዱ የሚሰራባቸው ተሽከርካሪዎች:',
		'holder'      => array(
			'የአባት ስም 1',
			'ሌሎች ስሞች 2',
			'የተወለድ ቦታ 3',
			'የተወለድ ቀን 4',
			'የቋሚ መኖሪያ ቦታ 5',
		),
		'categories'  => array(
			'A' => 'ሞተርሳይክሎች (ከጎን መንገድ ጋር ወይም ያለ), ለአካል ጉዳተኞች የተዘጋጁ ተሽከርካሪዎች እና ባለሶስት ጎማ ሞተር ተሽከርካሪዎች ክብደታቸው 400 ኪ.ግ (900 lb) ያልበለጠ።',
			'B' => 'ለሰዎች መጓጓዣ የሚጠቀሙ እና ከአሽከርካሪው መቀመጫ በተጨማሪ ከፍተኛው ስምንት መቀመጫዎች ያላቸው፣ ወይም ለእቃ መጓጓዣ የሚጠቀሙ እና ፍቃድ ያለው ከፍተኛ ክብደታቸው 3,500 ኪ.ግ (7,700 lb) ያልበለጠ። ይህ ክፍል ተሽከርካሪዎች ከቀላል ትሬለር ጋር ሊያገናኙ ይችላሉ።',
			'C' => 'ለእቃ መጓጓዣ የሚጠቀሙ እና ፍቃድ ያለው ከፍተኛ ክብደታቸው 3,500 ኪ.ግ (7,700 lb) የሚበልጥ። ይህ ክፍል ተሽከርካሪዎች ከቀላል ትሬለር ጋር ሊያገናኙ ይችላሉ።',
			'D' => 'ለሰዎች መጓጓዣ የሚጠቀሙ እና ከአሽከርካሪው መቀመጫ በተጨማሪ ከስምንት በላይ መቀመጫዎች ያላቸው። ይህ ክፍል ተሽከርካሪዎች ከቀላል ትሬለር ጋር ሊያገናኙ ይችላሉ።',
			'E' => 'ከላይ የተፈቀዱት የB፣ C ወይም D ክፍል ተሽከርካሪዎች፣ ከቀላል ትሬለር ውጪ ያለ ትሬለር ጋር።',
		),
		'notes'       => array(
			'የተሽከርካሪው “ፍቃድ ያለው ከፍተኛ ክብደት” ማለት ተሽከርካሪው እና ከፍተኛ ጭነቱ ለመንገድ ሲዘጋጅ ያለው ክብደት ማለት ነው።',
			'“ከፍተኛ ጭነት” ማለት በተሽከርካሪው የተመዘገበት አገር ባለሥልጣን የተገለጸው የጭነት ክብደት ነው። “ቀላል ትሬለሮች” ማለት ፍቃድ ያለው ከፍተኛ ክብደታቸው 750 ኪ.ግ (1,650 lb) ያልበለጠ ትሬለሮች ናቸው።',
		),
		'exclusion'   => array(
			'title'     => 'መከልከል',
			'intro'     => 'ይህን ፈቃድ ያዘው ሰው በ (አገር) መንዳት መብቱ ተነጥቋል',
			'country'   => 'ምክንያቱ',
			'reason'    => '',
			'seal'      => 'የባለሥልጣን<br>ማህተም',
			'place'     => 'ቦታ',
			'date'      => 'ቀን',
			'signature' => 'ፊርማ',
			'footnote'  => 'ከላይ ያለው ቦታ ከተሞላ ከሆነ፣ “መከልከል” ተብሎ የተመደበ ሌላ ቦታ ይጠቀሙ።',
		),
	),

	// ---- Page 22: Korean (folio 19) ----
	22 => array(
		'code'        => 'ko',
		'label'       => '한국어',
		'folio'       => '19',
		'font'        => 'unbatang',
		'weight'      => 'bold',
		'flag'        => array(),
		'lead_driver' => '운전자에 관한 사항:',
		'lead_valid'  => '허가증이 유효한 차량:',
		'holder'      => array(
			'성 1',
			'기타 이름 2',
			'출생지 3',
			'출생일 4',
			'영구 거주지 5',
		),
		'categories'  => array(
			'A' => '사이드카의 유무와 관계없는 오토바이, 장애인용 차량 및 공차 중량이 400 kg(900 lb)을 초과하지 않는 삼륜 자동차.',
			'B' => '운전자 좌석 외에 최대 8개의 좌석을 갖춘 승객 운송용 자동차 또는 허용 최대 중량이 3,500 kg(7,700 lb)을 초과하지 않는 화물 운송용 자동차. 이 범주의 차량은 경량 트레일러를 연결할 수 있다.',
			'C' => '허용 최대 중량이 3,500 kg(7,700 lb)을 초과하는 화물 운송용 자동차. 이 범주의 차량은 경량 트레일러를 연결할 수 있다.',
			'D' => '운전자 좌석 외에 8석을 초과하는 좌석을 갖춘 승객 운송용 자동차. 이 범주의 차량은 경량 트레일러를 연결할 수 있다.',
			'E' => '위에서 허가된 B, C 또는 D 범주의 자동차로서 경량 트레일러가 아닌 트레일러를 연결한 차량.',
		),
		'notes'       => array(
			'차량의 “허용 최대 중량”이란 도로 주행이 가능한 상태에서의 차량 중량과 최대 적재 중량의 합을 의미한다.',
			'“최대 적재 중량”이란 차량 등록 국가의 관할 기관이 신고한 적재 중량을 의미한다. “경량 트레일러”란 허용 최대 중량이 750 kg(1,650 lb)을 초과하지 않는 트레일러를 말한다.',
		),
		'exclusion'   => array(
			'title'     => '제외',
			'intro'     => '본 허가증의 소지자는 (국가)에서 운전할 권리를 박탈당한다',
			'country'   => '사유',
			'reason'    => '',
			'seal'      => '관할 기관의<br>인장 또는<br>도장',
			'place'     => '장소',
			'date'      => '날짜',
			'signature' => '서명',
			'footnote'  => '위의 공간이 이미 기입된 경우 “제외”를 위해 제공된 다른 공간을 사용한다.',
		),
	),

	// ---- Page 16: Hindi (folio 13) ----
	16 => array(
		'code'        => 'hi',
		'label'       => 'हिन्दी',
		'folio'       => '13',
		'font'        => 'freeserif',
		'flag'        => array(),
		'lead_driver' => 'चालक से संबंधित विवरण:',
		'lead_valid'  => 'वे वाहन जिनके लिए यह परमिट मान्य है:',
		'holder'      => array(
			'उपनाम 1',
			'अन्य नाम 2',
			'जन्म स्थान 3',
			'जन्म तिथि 4',
			'स्थायी निवास स्थान 5',
		),
		'categories'  => array(
			'A' => 'मोटरसाइकिलें, साइडकार के साथ या बिना, विकलांग वाहनों और तीन पहिया मोटर वाहन जिनका बिना लोड का वजन 400 किलोग्राम (900 पाउंड) से अधिक न हो।',
			'B' => 'यात्री परिवहन के लिए प्रयुक्त मोटर वाहन, जिनमें चालक की सीट के अतिरिक्त अधिकतम आठ सीटें हों, या माल परिवहन के लिए प्रयुक्त मोटर वाहन जिनका अनुमत अधिकतम वजन 3,500 किलोग्राम (7,700 पाउंड) से अधिक न हो। इस श्रेणी के वाहन हल्के ट्रेलर से जोड़े जा सकते हैं।',
			'C' => 'माल परिवहन के लिए प्रयुक्त मोटर वाहन जिनका अनुमत अधिकतम वजन 3,500 किलोग्राम (7,700 पाउंड) से अधिक हो। इस श्रेणी के वाहन हल्के ट्रेलर से जोड़े जा सकते हैं।',
			'D' => 'यात्री परिवहन के लिए प्रयुक्त मोटर वाहन, जिनमें चालक की सीट के अतिरिक्त आठ से अधिक सीटें हों। इस श्रेणी के वाहन हल्के ट्रेलर से जोड़े जा सकते हैं।',
			'E' => 'ऊपर अधिकृत B, C या D श्रेणी के मोटर वाहन, हल्के ट्रेलर के अलावा किसी अन्य ट्रेलर के साथ।',
		),
		'notes'       => array(
			'किसी वाहन का “अनुमत अधिकतम वजन” वाहन और उसके अधिकतम भार का कुल वजन होता है जब वह सड़क पर चलने के लिए तैयार हो।',
			'“अधिकतम भार” से तात्पर्य उस भार के वजन से है जिसे वाहन के पंजीकरण देश की सक्षम प्राधिकरण द्वारा घोषित किया गया हो। “हल्के ट्रेलर” वे हैं जिनका अनुमत अधिकतम वजन 750 किलोग्राम (1,650 पाउंड) से अधिक नहीं होता।',
		),
		'exclusion'   => array(
			'title'     => 'अपवर्जन',
			'intro'     => 'इस परमिट का धारक (देश) में वाहन चलाने के अधिकार से वंचित है',
			'country'   => 'कारण',
			'reason'    => '',
			'seal'      => 'प्राधिकरण<br>की मुहर या<br>छाप',
			'place'     => 'स्थान',
			'date'      => 'तिथि',
			'signature' => 'हस्ताक्षर',
			'footnote'  => 'यदि ऊपर दिया गया स्थान पहले से भरा हुआ हो, तो “अपवर्जन” के लिए निर्धारित किसी अन्य स्थान का उपयोग करें।',
		),
	),

	// ---- Page 21: Thai (folio 18) ----
	21 => array(
		'code'        => 'th',
		'label'       => 'ไทย',
		'folio'       => '18',
		'font'        => 'garuda',
		'flag'        => array(),
		'lead_driver' => 'ข้อมูลเกี่ยวกับผู้ขับขี่:',
		'lead_valid'  => 'ยานพาหนะที่ใบอนุญาตนี้ใช้ได้:',
		'holder'      => array(
			'นามสกุล 1',
			'ชื่ออื่น ๆ 2',
			'สถานที่เกิด 3',
			'วันเดือนปีเกิด 4',
			'ที่อยู่ถาวร 5',
		),
		'categories'  => array(
			'A' => 'รถจักรยานยนต์ มีหรือไม่มีพ่วงข้าง รถสำหรับผู้พิการ และยานยนต์สามล้อที่มีน้ำหนักเปล่าไม่เกิน 400 กก. (900 ปอนด์)',
			'B' => 'ยานยนต์ที่ใช้ในการขนส่งผู้โดยสารและมีที่นั่งนอกเหนือจากที่นั่งผู้ขับขี่ไม่เกินแปดที่นั่ง หรือยานยนต์ที่ใช้ในการขนส่งสินค้าและมีน้ำหนักรวมที่อนุญาตไม่เกิน 3,500 กก. (7,700 ปอนด์) ยานพาหนะในประเภทนี้อาจลากพ่วงด้วยรถพ่วงเบาได้',
			'C' => 'ยานยนต์ที่ใช้ในการขนส่งสินค้าและมีน้ำหนักรวมที่อนุญาตเกิน 3,500 กก. (7,700 ปอนด์) ยานพาหนะในประเภทนี้อาจลากพ่วงด้วยรถพ่วงเบาได้',
			'D' => 'ยานยนต์ที่ใช้ในการขนส่งผู้โดยสารและมีที่นั่งนอกเหนือจากที่นั่งผู้ขับขี่มากกว่าแปดที่นั่ง ยานพาหนะในประเภทนี้อาจลากพ่วงด้วยรถพ่วงเบาได้',
			'E' => 'ยานยนต์ประเภท B, C หรือ D ตามที่ได้รับอนุญาตข้างต้น พร้อมรถพ่วงที่ไม่ใช่รถพ่วงเบา',
		),
		'notes'       => array(
			'“น้ำหนักรวมที่อนุญาต” ของยานพาหนะหมายถึงน้ำหนักของยานพาหนะและน้ำหนักบรรทุกสูงสุดเมื่อพร้อมใช้งานบนถนน',
			'“น้ำหนักบรรทุกสูงสุด” หมายถึงน้ำหนักบรรทุกที่ประกาศโดยหน่วยงานที่มีอำนาจของประเทศที่จดทะเบียนยานพาหนะ “รถพ่วงเบา” หมายถึงรถพ่วงที่มีน้ำหนักรวมที่อนุญาตไม่เกิน 750 กก. (1,650 ปอนด์)',
		),
		'exclusion'   => array(
			'title'     => 'การยกเว้น',
			'intro'     => 'ผู้ถือใบอนุญาตนี้ถูกตัดสิทธิ์ในการขับขี่ใน',
			'country'   => '(ประเทศ)',
			'reason'    => 'ด้วยเหตุผล',
			'seal'      => 'ตราประทับ<br>หรือแสตมป์<br>ของหน่วยงาน',
			'place'     => 'สถานที่',
			'date'      => 'วันที่',
			'signature' => 'ลายเซ็น',
			'footnote'  => 'หากพื้นที่ด้านบนถูกกรอกแล้ว ให้ใช้พื้นที่อื่นใดที่จัดไว้สำหรับ “การยกเว้น”',
		),
	),

);
