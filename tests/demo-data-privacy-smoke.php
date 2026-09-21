<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

$source = file_get_contents( dirname( __DIR__ ) . '/includes/helpers.php' );
if ( false === $source ) {
	throw new RuntimeException( 'Die AG-Demoquelle konnte nicht gelesen werden.' );
}

$start = strpos( $source, 'function flz_ags_demo_course_from_page' );
$end   = strpos( $source, 'function flz_ags_page_plain_text', $start ?: 0 );
if ( false === $start || false === $end ) {
	throw new RuntimeException( 'Der AG-Demo-Konverter wurde nicht gefunden.' );
}

$converter = substr( $source, $start, $end - $start );
$forbidden = array(
	'get_the_title',
	'post_excerpt',
	'flz_ags_demo_leader_from_page',
	'flz_ags_page_image_url',
);

foreach ( $forbidden as $value ) {
	if ( str_contains( $converter, $value ) ) {
		throw new RuntimeException( 'Die AG-Demo übernimmt weiterhin personenbezogene oder redaktionelle Quelldaten.' );
	}
}

foreach ( array( "'leader_name' => ''", "'image_url' => ''", "'title' => sprintf('Demo-AG %02d'" ) as $required ) {
	if ( ! str_contains( $converter, $required ) ) {
		throw new RuntimeException( 'Der AG-Demo-Konverter erzeugt keine klar synthetischen Ausgabefelder.' );
	}
}

$slots_start = strpos( $source, 'function flz_ags_demo_slots_from_page_text' );
$slots_end   = strpos( $source, 'function flz_ags_weekday_from_label', $slots_start ?: 0 );
if ( false === $slots_start || false === $slots_end ) {
	throw new RuntimeException( 'Der AG-Demo-Slot-Konverter wurde nicht gefunden.' );
}

$slot_converter = substr( $source, $slots_start, $slots_end - $slots_start );
if ( ! str_contains( $slot_converter, '$room = \'\';' ) || str_contains( $slot_converter, "'Raum'" ) ) {
	throw new RuntimeException( 'Die AG-Demo übernimmt weiterhin frei gepflegte Raumangaben.' );
}

echo "OK: flz_ags demo data privacy smoke test\n";
