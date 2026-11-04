<?php

defined('ABSPATH') || exit;
?>
<p class="description">Sie sehen alle AG-Anmeldungen des gewählten Zeitraums. Änderungen, Exporte und Importe sind nicht möglich.</p>
<?php
$ui = flz_ui();
echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags-registrations'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular.
echo $ui->input('text', array('name' => 'school_year', 'label' => 'Zeitraum', 'value' => $school_year)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
echo $ui->button_filter(array('label' => 'Anmeldungen anzeigen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende.
?>
<?php
$empty_message = 'Keine Anmeldungen gefunden.';
require __DIR__ . '/admin-readonly-registration-table.php';
