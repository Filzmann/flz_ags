<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
$export_url = wp_nonce_url(
	admin_url(
		'admin-post.php?action=flz_ags_export_csv'
		. '&school_year=' . rawurlencode($school_year)
	),
	'flz_ags_export_csv'
);
?>
<?php echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags-registrations'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<?php echo $ui->input('text', array('name' => 'school_year', 'label' => 'Schuljahr', 'value' => $school_year)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $ui->field(array('type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $status, 'options' => flz_ags_status_labels())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $ui->button_filter(array('label' => 'Anmeldungen filtern')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped das CSV-Panel inklusive Link-Attribute.
echo $ui->csv_panel(array(
	'title'       => 'Anmeldungs-Backup als CSV',
	'description' => 'Lädt alle Status des gewählten Schuljahrs in einem wiederherstellbaren Format herunter.',
	'export'      => array(
		'href'  => esc_url($export_url),
		'label' => 'Anmeldungs-CSV herunterladen',
	),
));
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
?>

<section class="flz-ui-panel flz-ui-csv-panel">
	<h3 class="flz-ui-panel__title">Anmeldungs-CSV hochladen</h3>
	<p class="flz-ui-panel__description">Stellt Anmeldungen aus einem zuvor heruntergeladenen Backup wieder her. AG und Termin werden über Schuljahr, AG-Slug, Wochentag, Uhrzeit und Raum zugeordnet. Nicht auflösbare Zeilen werden übersprungen und einzeln gemeldet; es werden keine Bestätigungsmails versendet.</p>
	<div class="flz-ui-csv-panel__actions">
		<?php
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped Formular, Nonce und Felder vollständig.
		echo $ui->form_start(array(
			'method'  => 'post',
			'action'  => esc_url(admin_url('admin-post.php')),
			'enctype' => 'multipart/form-data',
			'nonce'   => 'flz_ags_import_registrations_csv',
			'hidden'  => array(
				'action'      => 'flz_ags_import_registrations_csv',
				'school_year' => $school_year,
			),
		));
		echo $ui->input('file', array(
			'name'   => 'registration-csv',
			'id'     => 'flz-ags-registration-csv-import',
			'label'  => 'Anmeldungs-CSV-Datei',
			'accept' => '.csv',
		));
		echo $ui->field(array(
			'type'        => 'checkbox',
			'name'        => 'confirm_registration_import',
			'label'       => 'Ich bestätige, dass diese Datei personenbezogene Anmeldungsdaten enthält und wiederhergestellt werden soll.',
			'description' => 'Vorhandene identische Backup-Datensätze werden aktualisiert. Andere aktive Anmeldungen derselben Schüler*in bleiben geschützt.',
		));
		echo $ui->button_upload(array(
			'label'   => 'Anmeldungs-CSV hochladen',
			'confirm' => 'Anmeldungsdaten jetzt direkt importieren?',
		));
		echo $ui->form_end();
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>
</section>

<?php if (is_array($csv_report)) : ?>
	<section class="notice <?php echo !empty($csv_report['error']) ? 'notice-error' : 'notice-info'; ?> inline flz-ags-csv-report" aria-labelledby="flz-ags-registration-csv-report-title">
		<h3 id="flz-ags-registration-csv-report-title">Hinweise zum letzten Anmeldungs-Import</h3>
		<?php if (empty($csv_report['error'])) : ?>
			<p><?php echo esc_html((int) $csv_report['created'] . ' neu, ' . (int) $csv_report['updated'] . ' aktualisiert, ' . (int) $csv_report['skipped'] . ' übersprungen.'); ?></p>
		<?php endif; ?>
		<?php if (!empty($csv_report['warnings'])) : ?>
			<ul>
				<?php foreach ((array) $csv_report['warnings'] as $warning) : ?>
					<li><?php echo esc_html((string) $warning); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php endif; ?>

<table class="widefat striped">
	<thead>
		<tr>
			<th>Schüler*in</th>
			<th>Klasse</th>
			<th>AG</th>
			<th>Slot</th>
			<th>E-Mail Schüler*in</th>
			<th>Status</th>
			<th>Datum</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php if (empty($registrations)) : ?>
			<tr><td colspan="8">Keine Anmeldungen gefunden.</td></tr>
		<?php endif; ?>
		<?php foreach ($registrations as $registration) : ?>
			<tr>
				<td><?php echo esc_html($registration->student_last_name . ', ' . $registration->student_first_name); ?></td>
				<td><?php echo esc_html(flz_ags_class_label((string) $registration->class_name)); ?></td>
				<td><?php echo esc_html($registration->title); ?></td>
				<td>
					<?php
					echo esc_html(
						flz_ags_weekday_label($registration->weekday)
						. ', '
						. flz_ags_format_time($registration->start_time)
						. '–'
						. flz_ags_format_time($registration->end_time)
						. ($registration->room ? ', ' . $registration->room : '')
					);
					?>
				</td>
				<td><?php echo esc_html($registration->student_email); ?></td>
				<td><?php echo esc_html(flz_ags_status_label($registration->status)); ?></td>
				<td><?php echo esc_html(flz_ui_format_datetime($registration->created_at, (string) $registration->created_at)); ?></td>
				<td>
					<?php
					if ($registration->status === 'active') {
						// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped das Aktionsformular inklusive URL, Nonce und Hidden Fields.
						echo $ui->action_form_button(array(
							'preset' => 'reset',
							'label' => 'Anmeldung widerrufen',
							'method' => 'post',
							'action' => esc_url(admin_url('admin-post.php')),
							'nonce' => 'flz_ags_update_registration',
							'hidden' => array(
								'action' => 'flz_ags_update_registration',
								'registration_id' => absint($registration->id),
								'new_status' => 'withdrawn',
							),
						));
						// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
