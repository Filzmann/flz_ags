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
<table class="widefat striped">
    <thead><tr><th>Schüler*in</th><th>Klasse</th><th>AG</th><th>Termin</th><th>E-Mail</th><th>Status</th><th>Angemeldet am</th></tr></thead>
    <tbody>
        <?php if (empty($registrations)) : ?><tr><td colspan="7">Keine Anmeldungen gefunden.</td></tr><?php endif; ?>
        <?php foreach ($registrations as $registration) : ?>
            <tr>
                <td><?php echo esc_html(trim((string) $registration->student_first_name . ' ' . (string) $registration->student_last_name)); ?></td>
                <td><?php echo esc_html(flz_ags_class_label((string) $registration->class_name)); ?></td>
                <td><?php echo esc_html((string) $registration->title); ?></td>
                <td><?php echo esc_html(flz_ags_weekday_label((int) $registration->weekday) . ', ' . flz_ags_format_time((string) $registration->start_time) . '–' . flz_ags_format_time((string) $registration->end_time)); ?></td>
                <td><?php echo esc_html((string) $registration->student_email); ?></td>
                <td><?php echo esc_html(flz_ags_status_labels()[(string) $registration->status] ?? (string) $registration->status); ?></td>
                <td><?php echo esc_html(flz_ui_format_datetime($registration->created_at, (string) $registration->created_at)); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
