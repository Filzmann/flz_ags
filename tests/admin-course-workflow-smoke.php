<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/tmp/' );

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$template_source = file_get_contents( dirname( __DIR__ ) . '/templates/admin-course-form.php' );
$plugin_source   = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
$style_source    = file_get_contents( dirname( __DIR__ ) . '/assets/css/flz-ags.css' );

require_once dirname( __DIR__ ) . '/includes/helpers.php';

if ( false === $template_source || false === $plugin_source || false === $style_source ) {
	throw new RuntimeException( 'AG-Formular, Plugin-Controller oder Stylesheet konnte nicht gelesen werden.' );
}

$save_handler_start = strpos( $plugin_source, 'public function handle_save_course(): void' );
$save_handler_end   = strpos( $plugin_source, 'public function render_admin_settings_page(): void', $save_handler_start ?: 0 );
if ( false === $save_handler_start || false === $save_handler_end ) {
	throw new RuntimeException( 'Der AG-Speichervorgang konnte im Plugin-Controller nicht gefunden werden.' );
}
$save_handler_source = substr( $plugin_source, $save_handler_start, $save_handler_end - $save_handler_start );

$close_redirect = flz_ags_course_save_redirect_args( 17, true, true );
$new_redirect   = flz_ags_course_save_redirect_args( 17, true, false );
$edit_redirect  = flz_ags_course_save_redirect_args( 17, false, false );

$checks = array(
	array(
		$close_redirect === array( 'page' => 'flz-ags', 'saved' => 1 ),
		'„Speichern und schließen“ führt nicht zur AG-Liste oder verliert im Doppelfall den Vorrang.',
	),
	array(
		$new_redirect === array( 'page' => 'flz-ags', 'action' => 'new', 'saved' => 1 ),
		'„Speichern und neu“ führt nicht zu einem leeren AG-Formular.',
	),
	array(
		$edit_redirect === array( 'page' => 'flz-ags', 'action' => 'edit', 'course_id' => 17, 'saved' => 1 ),
		'Normales Speichern führt nicht zurück zur bearbeiteten AG.',
	),
	array(
		str_contains( $template_source, "'label' => 'Speichern und neu'" )
		&& str_contains( $template_source, "\$ui->button_save(array('label' => 'Speichern und neu', 'class' => 'flz-ags-save-and-new', 'type' => 'submit'" )
		&& str_contains( $style_source, '.flz-ags-save-and-new::after' )
		&& str_contains( $style_source, 'content: "+";' )
		&& str_contains( $template_source, "'name' => 'save_and_new'" ),
		'Der „Speichern und neu“-Button verwendet nicht das kombinierte Disketten-/Plus-Icon als Submit.',
	),
	array(
		str_contains( $template_source, "'label' => 'Speichern und schließen'" )
		&& str_contains( $template_source, "\$ui->button_save(array('label' => 'Speichern und schließen', 'class' => 'flz-ags-save-and-close', 'type' => 'submit'" )
		&& str_contains( $style_source, '.flz-ags-save-and-close::after' )
		&& str_contains( $style_source, 'content: "×";' )
		&& str_contains( $template_source, "'name' => 'save_and_close'" ),
		'Der „Speichern und schließen“-Button verwendet nicht sein kombiniertes Disketten-/Schließen-Icon als Submit.',
	),
	array(
		str_contains( $save_handler_source, "isset(\$_POST['save_and_new'])" ),
		'Der Speichervorgang wertet „Speichern und neu“ nicht aus.',
	),
	array(
		str_contains( $save_handler_source, "isset(\$_POST['save_and_close'])" ),
		'Der Speichervorgang wertet „Speichern und schließen“ nicht aus.',
	),
	array(
		strpos( $save_handler_source, '$this->assert_admin_permission();' )
		< strpos( $save_handler_source, "isset(\$_POST['save_and_close'])" )
		&& strpos( $save_handler_source, "check_admin_referer('flz_ags_save_course');" )
		< strpos( $save_handler_source, "isset(\$_POST['save_and_close'])" ),
		'Die neue Speicheraktion wird ausgewertet, bevor Capability und Nonce geprüft wurden.',
	),
	array(
		str_contains(
			$save_handler_source,
			'$redirect_args = flz_ags_course_save_redirect_args($course_id, $save_and_new, $save_and_close);'
		),
		'Der Speichervorgang verwendet nicht die geprüfte Zielauswahl für alle drei Speicheraktionen.',
	),
	array(
		! str_contains( $save_handler_source, '$registration_open && $detail_page_id <= 0' ),
		'Eine fehlende Detailseite verhindert weiterhin das Speichern einer AG.',
	),
	array(
		str_contains( $save_handler_source, '$registration_open && $detail_page_id > 0 && get_post_status($detail_page_id)' ),
		'Eine ausgewählte Detailseite wird bei geöffneter Anmeldung nicht mehr auf Veröffentlichung geprüft.',
	),
);

foreach ( $checks as list( $passed, $message ) ) {
	if ( ! $passed ) {
		throw new RuntimeException( $message );
	}
}

echo "OK: flz_ags admin course workflow smoke test\n";
