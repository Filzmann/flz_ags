<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/tmp/' );

function current_datetime(): DateTimeImmutable {
	return new DateTimeImmutable( '2026-06-01 12:00:00', new DateTimeZone( 'Europe/Berlin' ) );
}

function current_time( string $type ): string {
	return current_datetime()->format( $type );
}

function get_option( string $name, $default = false ) {
	return $default;
}

function apply_filters( string $hook, $value ) {
	return $value;
}

final class FLZ_AGS_Course {
	public static function find_school_years(): array {
		return array( '2024/2025', '2026/2027', 'ungueltig', '2024/2025' );
	}
}

$registered_blocks = array();
function flz_ui_register_shortcode_block( array $config ): void {
	$GLOBALS['registered_blocks'][] = $config;
}

require_once dirname( __DIR__ ) . '/includes/helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-flz-ags.php';

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

function flz_ags_block_year_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$timezone = new DateTimeZone( 'Europe/Berlin' );
flz_ags_block_year_assert(
	'2025/2026' === flz_ags_default_school_year_for_date( new DateTimeImmutable( '2026-07-08 23:59:59', $timezone ) ),
	'Vor Beginn der Berliner Sommerferien 2026 ist nicht das laufende Schuljahr vorausgewählt.'
);
flz_ags_block_year_assert(
	'2026/2027' === flz_ags_default_school_year_for_date( new DateTimeImmutable( '2026-07-09 00:00:00', $timezone ) ),
	'Am ersten Berliner Sommerferientag 2026 wird nicht auf das kommende Schuljahr gewechselt.'
);
flz_ags_block_year_assert(
	'2027/2028' === flz_ags_default_school_year_for_date( new DateTimeImmutable( '2027-07-01 00:00:00', $timezone ) ),
	'Der amtliche Berliner Sommerferienbeginn 2027 wird nicht berücksichtigt.'
);
flz_ags_block_year_assert(
	'2030/2031' === flz_ags_default_school_year_for_date( new DateTimeImmutable( '2030-07-04 00:00:00', $timezone ) ),
	'Der amtliche Berliner Sommerferienbeginn 2030 wird nicht berücksichtigt.'
);
flz_ags_block_year_assert(
	'2030/2031' === flz_ags_default_school_year_for_date( new DateTimeImmutable( '2031-07-31 12:00:00', $timezone ) )
	&& '2031/2032' === flz_ags_default_school_year_for_date( new DateTimeImmutable( '2031-08-01 00:00:00', $timezone ) ),
	'Der dokumentierte August-Fallback für noch nicht veröffentlichte Ferienjahre ist nicht stabil.'
);

$selection = flz_ags_block_school_year_selection();
flz_ags_block_year_assert( '2025/2026' === $selection['default'], 'Das laufende Schuljahr ist nicht der Block-Standard.' );
flz_ags_block_year_assert(
	array(
		'2026/2027' => '2026/2027',
		'2025/2026' => '2025/2026',
		'2024/2025' => '2024/2025',
	) === $selection['options'],
	'Vorhandene Schuljahre werden nicht validiert, dedupliziert und absteigend sortiert.'
);

$plugin = ( new ReflectionClass( FLZ_AGS_Plugin::class ) )->newInstanceWithoutConstructor();
$plugin->register_blocks();
flz_ags_block_year_assert( 2 === count( $registered_blocks ), 'Die beiden AG-Shortcode-Blöcke wurden nicht registriert.' );
foreach ( $registered_blocks as $block ) {
	$field = $block['fields']['school_year'] ?? array();
	flz_ags_block_year_assert(
		'select' === ( $field['control'] ?? '' ) && $selection['options'] === ( $field['options'] ?? array() ),
		'Das Schuljahr wird in einem AG-Block nicht als Dropdown der verfügbaren Schuljahre registriert.'
	);
	flz_ags_block_year_assert(
		$selection['default'] === ( $block['attributes']['school_year']['default'] ?? '' ),
		'Der AG-Block verwendet nicht das kalendarisch passende Standardschuljahr.'
	);
}

echo "OK: flz_ags Gutenberg school year smoke test\n";
