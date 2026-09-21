<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/tmp/' );

function sanitize_key( $value ): string {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

function absint( $value ): int {
	return abs( (int) $value );
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$plugin_source   = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
$frontend_source = file_get_contents( dirname( __DIR__ ) . '/frontend/frontend.php' );
$template_source = file_get_contents( dirname( __DIR__ ) . '/templates/frontend-course-card.php' );
$script_source   = file_get_contents( dirname( __DIR__ ) . '/assets/js/flz-ags.js' );

if ( false === $plugin_source || false === $frontend_source || false === $template_source || false === $script_source ) {
	throw new RuntimeException( 'Controller, Frontend-Renderer, AG-Karten-Template oder Frontend-Script konnte nicht gelesen werden.' );
}

$checks = array(
	array(
		str_contains( $plugin_source, '$course->registration_html = $this->shortcode_registration' ),
		'Die AG-Liste bereitet für eine AG ohne Detailseite keine eingeblendete Anmeldung vor.',
	),
	array(
		str_contains( $plugin_source, 'flz_ags_course_detail_page_id($course) <= 0' )
		&& str_contains( $plugin_source, '!empty($course->registration_open)' ),
		'Die eingeblendete Anmeldung ist nicht auf AGs ohne Detailseite und mit geöffneter Anmeldung begrenzt.',
	),
	array(
		str_contains( $plugin_source, '$detail_page_id > 0' )
		&& ! str_contains( $plugin_source, '($detail_page_id <= 0 || $detail_page_id !== (int) get_queried_object_id())' ),
		'Der Anmelde-Shortcode lehnt AGs ohne Detailseite weiterhin ab.',
	),
	array(
		substr_count( $plugin_source, '$this->is_registration_post_for_course((int) $course->id)' ) >= 2,
		'POST-Verarbeitung und Vorbefüllung sind nicht auf das betroffene AG-Formular begrenzt.',
	),
	array(
		str_contains( $frontend_source, 'floating_action_panel' )
		&& str_contains( $frontend_source, "'button_label' => 'Anmeldung'" )
		&& str_contains( $frontend_source, "'button_variant' => 'primary'" )
		&& str_contains( $frontend_source, "'button_icon' => 'view'" )
		&& str_contains( $frontend_source, "'content' => \$registration_html" ),
		'Der Panel-Trigger verwendet nicht Text und Optik des Detailbuttons.',
	),
	array(
		str_contains( $template_source, '$registration_panel_html' )
		&& ! str_contains( $template_source, 'class="flz-ags-course-entry__registration"' ),
		'Das Karten-Template hängt das Formular weiterhin sichtbar an, statt das verborgene Panel zu rendern.',
	),
	array(
		str_contains( $plugin_source, '$course->registration_panel_open = $this->is_registration_post_for_course' ),
		'Das Panel der abgesendeten AG wird nach einem Anmelde-POST nicht wieder geöffnet.',
	),
	array(
		str_contains( $script_source, "item.closest('.flz-ags') !== scope" )
		&& str_contains( $script_source, "item.getAttribute('data-weekday')" ),
		'Der Listenfilter greift in eingebettete Anmeldeformulare ein oder verändert fremde Radiofelder.',
	),
);

$style_source = file_get_contents( dirname( __DIR__ ) . '/assets/css/flz-ags.css' );
if (
	false === $style_source
	|| ! str_contains( $style_source, '.flz-ags-registration-panel.flz-ags-card-registration-panel .flz-ui-floating-panel__trigger' )
	|| ! str_contains( $style_source, 'position: static' )
	|| ! str_contains( $style_source, 'background: var(--flz-ui-color-primary)' )
	|| ! str_contains( $style_source, 'border-radius: var(--flz-ui-radius)' )
	|| ! str_contains( $style_source, 'content: none' )
) {
	throw new RuntimeException( 'Der Panel-Trigger übernimmt in der AG-Karte weiterhin die auffällige Floating-CTA-Optik.' );
}

foreach ( $checks as list( $passed, $message ) ) {
	if ( ! $passed ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-flz-ags.php';
$plugin = ( new ReflectionClass( FLZ_AGS_Plugin::class ) )->newInstanceWithoutConstructor();
$post_match = new ReflectionMethod( FLZ_AGS_Plugin::class, 'is_registration_post_for_course' );
$post_match->setAccessible( true );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['flz_ags_course_id'] = '17';
if ( true !== $post_match->invoke( $plugin, 17 ) || false !== $post_match->invoke( $plugin, 18 ) ) {
	throw new RuntimeException( 'Ein Anmelde-POST wird nicht ausschließlich seiner eigenen AG zugeordnet.' );
}

$_SERVER['REQUEST_METHOD'] = 'GET';
if ( false !== $post_match->invoke( $plugin, 17 ) ) {
	throw new RuntimeException( 'Eine GET-Anfrage wird fälschlich als Anmelde-POST behandelt.' );
}

echo "OK: flz_ags no-detail registration smoke test\n";
