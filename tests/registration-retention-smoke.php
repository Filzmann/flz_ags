<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$plugin = file_get_contents( dirname( __DIR__ ) . '/includes/class-flz-ags.php' );
$settings = file_get_contents( dirname( __DIR__ ) . '/templates/admin-settings.php' );
$activation = file_get_contents( dirname( __DIR__ ) . '/activate-deactivate.php' );
$entrypoint = file_get_contents( dirname( __DIR__ ) . '/flz_ags.php' );
$model = file_get_contents( dirname( __DIR__ ) . '/includes/models/class-flz-ags-registration.php' );
if ( false === $plugin || false === $settings || false === $activation || false === $entrypoint || false === $model ) {
	throw new RuntimeException( 'Die Quellen für den Datenschutzvertrag konnten nicht gelesen werden.' );
}

function flz_ags_retention_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

flz_ags_retention_assert( str_contains( $activation, "add_option('flz_ags_registration_retention_enabled', 0)" ), 'Automatische Löschung ist nicht sicher standardmäßig deaktiviert.' );
flz_ags_retention_assert( str_contains( $activation, "add_option('flz_ags_registration_retention_months', 24)" ), 'Es fehlt eine nachvollziehbare Standardfrist.' );
flz_ags_retention_assert( str_contains( $settings, 'Alte Anmeldungen automatisch löschen' ), 'Die Datenschutzeinstellung ist nicht verständlich beschriftet.' );
flz_ags_retention_assert( str_contains( $settings, "'name' => 'registration_retention_months'" ), 'Die Aufbewahrungsfrist ist nicht konfigurierbar.' );
flz_ags_retention_assert( str_contains( $settings, 'Anmeldedatum' ), 'Die UI erklärt die maßgebliche Fristbasis nicht.' );
flz_ags_retention_assert( str_contains( $plugin, "add_action('flz_ags_daily_registration_cleanup'" ), 'Der tägliche Aufräumjob ist nicht registriert.' );
flz_ags_retention_assert( str_contains( $plugin, "if (!(bool) get_option('flz_ags_registration_retention_enabled', 0))" ), 'Der Cron-Job löscht möglicherweise trotz deaktivierter Datenschutzregel.' );
flz_ags_retention_assert( str_contains( $plugin, "add_action('admin_post_flz_ags_delete_old_registrations'" ), 'Die manuelle Löschaktion ist nicht registriert.' );
flz_ags_retention_assert( str_contains( $entrypoint, "register_deactivation_hook(__FILE__, 'flz_ags_deactivate_plugin')" ), 'Die Deaktivierung räumt den Cron-Termin nicht auf.' );
flz_ags_retention_assert( str_contains( $model, 'WHERE created_at < %s' ), 'Die Löschfrist basiert nicht auf dem ursprünglichen Anmeldedatum.' );
flz_ags_retention_assert( ! str_contains( $model, 'WHERE updated_at < %s' ), 'Eine spätere Statusänderung verfälscht die Löschfrist.' );

$manual_start = strpos( $plugin, 'public function handle_delete_old_registrations(): void' );
$manual_end = strpos( $plugin, 'public function handle_import_registrations_csv(): void', $manual_start ?: 0 );
flz_ags_retention_assert( false !== $manual_start && false !== $manual_end, 'Die manuelle Löschaktion konnte nicht isoliert werden.' );
$manual = substr( $plugin, $manual_start, $manual_end - $manual_start );
flz_ags_retention_assert( str_contains( $manual, '$this->assert_admin_permission();' ), 'Die manuelle Löschung prüft keine Berechtigung.' );
flz_ags_retention_assert( str_contains( $manual, "check_admin_referer('flz_ags_delete_old_registrations')" ), 'Die manuelle Löschung prüft keinen Nonce.' );
flz_ags_retention_assert( str_contains( $manual, "isset(\$_POST['confirm_delete_old_registrations'])" ), 'Die manuelle Löschung verlangt keine bewusste Bestätigung.' );
flz_ags_retention_assert( str_contains( $manual, 'FLZ_AGS_Model::transaction(' ), 'Die manuelle Löschung ist nicht atomar.' );

echo "OK: flz_ags registration retention smoke test\n";
