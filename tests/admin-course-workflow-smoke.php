<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$template_source = file_get_contents( dirname( __DIR__ ) . '/templates/admin-course-form.php' );
$plugin_source   = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
$style_source    = file_get_contents( dirname( __DIR__ ) . '/assets/css/flz-ags.css' );

if ( false === $template_source || false === $plugin_source || false === $style_source ) {
	throw new RuntimeException( 'AG-Formular, Plugin-Controller oder Stylesheet konnte nicht gelesen werden.' );
}

$save_handler_start = strpos( $plugin_source, 'public function handle_save_course(): void' );
$save_handler_end   = strpos( $plugin_source, 'public function render_admin_settings_page(): void', $save_handler_start ?: 0 );
if ( false === $save_handler_start || false === $save_handler_end ) {
	throw new RuntimeException( 'Der AG-Speichervorgang konnte im Plugin-Controller nicht gefunden werden.' );
}
$save_handler_source = substr( $plugin_source, $save_handler_start, $save_handler_end - $save_handler_start );

$checks = array(
	array(
		str_contains( $template_source, "'label' => 'Speichern und neu'" )
		&& str_contains( $template_source, "\$ui->button_save(array('label' => 'Speichern und neu', 'class' => 'flz-ags-save-and-new', 'type' => 'submit'" )
		&& str_contains( $style_source, '.flz-ags-save-and-new::after' )
		&& str_contains( $style_source, 'content: "+";' )
		&& str_contains( $template_source, "'name' => 'save_and_new'" ),
		'Der „Speichern und neu“-Button verwendet nicht das kombinierte Disketten-/Plus-Icon als Submit.',
	),
	array(
		str_contains( $save_handler_source, "isset(\$_POST['save_and_new'])" ),
		'Der Speichervorgang wertet „Speichern und neu“ nicht aus.',
	),
	array(
		str_contains( $save_handler_source, '$redirect_args = $save_and_new' )
		&& str_contains( $save_handler_source, "? array('page' => 'flz-ags', 'action' => 'new', 'saved' => 1)" )
		&& str_contains( $save_handler_source, ": array('page' => 'flz-ags', 'action' => 'edit', 'course_id' => \$course_id, 'saved' => 1)" ),
		'Der Speichervorgang unterscheidet die Zielseiten für „neu“ und „bearbeiten“ nicht.',
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
