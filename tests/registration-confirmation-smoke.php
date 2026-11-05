<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$model = file_get_contents($root . '/includes/models/class-flz-ags-registration.php');
$plugin = file_get_contents($root . '/includes/class-flz-ags.php');
$activation = file_get_contents($root . '/activate-deactivate.php');
$settings = file_get_contents($root . '/templates/admin-settings.php');
$helpers = file_get_contents($root . '/includes/helpers.php');

if (!is_string($model) || !is_string($plugin) || !is_string($activation) || !is_string($settings) || !is_string($helpers)) {
	throw new RuntimeException('Die Quellen für den AG-Bestätigungsvertrag konnten nicht gelesen werden.');
}

function flz_ags_confirmation_assert(bool $condition, string $message): void
{
	if (!$condition) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-Testfehler wird nicht gerendert.
		throw new RuntimeException($message);
	}
}

flz_ags_confirmation_assert(str_contains($model, 'is_confirmed tinyint(1) NOT NULL DEFAULT 1'), 'Das boolesche Bestätigungsmerkmal fehlt im Anmeldemodell.');
flz_ags_confirmation_assert(str_contains($model, 'activation_token') && str_contains($model, 'activation_expires_at') && str_contains($model, 'activation_warning_sent_at'), 'Token-, Frist- und Warnmarkierung fehlen im Anmeldemodell.');
flz_ags_confirmation_assert(str_contains($plugin, 'handle_registration_activation') && str_contains($plugin, 'hash_equals'), 'Der öffentliche Aktivierungspfad fehlt oder prüft den Token nicht sicher.');
flz_ags_confirmation_assert(str_contains($plugin, 'flz_ags_hourly_unconfirmed_registration_release') && str_contains($plugin, 'handle_scheduled_unconfirmed_registration_release'), 'Der stündliche Freigabejob fehlt.');
flz_ags_confirmation_assert(str_contains($plugin, "'is_confirmed' => 0") && str_contains($plugin, '$registration->is_confirmed = 1'), 'Neue AG-Anmeldungen oder ihre Bestätigung setzen das boolesche Merkmal nicht korrekt.');
flz_ags_confirmation_assert(str_contains($plugin, 'count_reserved_registrations') && str_contains($model, "status = 'active'"), 'Unbestätigte AG-Anmeldungen reservieren keine Kapazität.');
flz_ags_confirmation_assert(str_contains($activation, "add_option('flz_ags_unconfirmed_registration_release_enabled', 0)"), 'Die automatische Freigabe ist nicht sicher standardmäßig deaktiviert.');
flz_ags_confirmation_assert(str_contains($settings, 'Vorgemerkte Anmeldungen automatisch freigeben') && str_contains($settings, 'Warnung vor der Freigabe'), 'Die konfigurierbaren Freigabe- und Warnoptionen fehlen.');

echo "OK: flz_ags registration confirmation contract smoke test\n";
