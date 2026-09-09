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
$class_options = array();
$course_options = array();
$slot_options = array();
foreach ((array) $registrations as $registration_option) {
	$class_value = (string) ($registration_option->class_name ?? '');
	$course_value = (string) ($registration_option->title ?? '');
	$slot_value = flz_ags_weekday_label((int) ($registration_option->weekday ?? 0))
		. ', ' . flz_ags_format_time((string) ($registration_option->start_time ?? ''))
		. '–' . flz_ags_format_time((string) ($registration_option->end_time ?? ''))
		. (!empty($registration_option->room) ? ', ' . (string) $registration_option->room : '');
	if ($class_value !== '') {
		$class_options[$class_value] = flz_ags_class_label($class_value);
	}
	if ($course_value !== '') {
		$course_options[$course_value] = $course_value;
	}
	if ($slot_value !== '') {
		$slot_options[$slot_value] = $slot_value;
	}
}
natcasesort($class_options);
natcasesort($course_options);
natcasesort($slot_options);
?>
<?php echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags-registrations'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<?php echo $ui->input('text', array('name' => 'school_year', 'label' => 'Schuljahr', 'value' => $school_year)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $ui->button_filter(array('label' => 'Schuljahr anzeigen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
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

<div data-flz-ags-registration-list>
<p class="screen-reader-text" aria-live="polite" data-flz-ags-registration-results-status></p>
<table class="widefat striped">
	<thead>
		<tr>
			<th scope="col" aria-sort="none">
				<button type="button" class="flz-ags-admin-sort-button" data-flz-ags-registration-sort="student">Schüler*in</button>
				<details class="flz-ags-admin-column-filter"><summary>Filtern</summary>
					<?php echo $ui->input('search', array('name' => 'registration_student_filter', 'label' => 'Schüler*in', 'placeholder' => 'Name suchen', 'attrs' => array('data-flz-ags-registration-filter' => 'student', 'data-flz-ags-filter-mode' => 'contains'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</details>
			</th>
			<th scope="col" aria-sort="none">
				<button type="button" class="flz-ags-admin-sort-button" data-flz-ags-registration-sort="class">Klasse</button>
				<details class="flz-ags-admin-column-filter"><summary>Filtern</summary>
					<?php echo $ui->field(array('type' => 'select', 'name' => 'registration_class_filter', 'label' => 'Klasse', 'placeholder' => 'alle Klassen', 'options' => $class_options, 'attrs' => array('data-flz-ags-registration-filter' => 'class'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</details>
			</th>
			<th scope="col" aria-sort="none">
				<button type="button" class="flz-ags-admin-sort-button" data-flz-ags-registration-sort="course">AG</button>
				<details class="flz-ags-admin-column-filter"><summary>Filtern</summary>
					<?php echo $ui->field(array('type' => 'select', 'name' => 'registration_course_filter', 'label' => 'AG', 'placeholder' => 'alle AGs', 'options' => $course_options, 'attrs' => array('data-flz-ags-registration-filter' => 'course'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</details>
			</th>
			<th scope="col" aria-sort="none">
				<button type="button" class="flz-ags-admin-sort-button" data-flz-ags-registration-sort="slot">Slot</button>
				<details class="flz-ags-admin-column-filter"><summary>Filtern</summary>
					<?php echo $ui->field(array('type' => 'select', 'name' => 'registration_slot_filter', 'label' => 'Slot', 'placeholder' => 'alle Slots', 'options' => $slot_options, 'attrs' => array('data-flz-ags-registration-filter' => 'slot'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</details>
			</th>
			<th scope="col" aria-sort="none">
				<button type="button" class="flz-ags-admin-sort-button" data-flz-ags-registration-sort="email">E-Mail Schüler*in</button>
				<details class="flz-ags-admin-column-filter"><summary>Filtern</summary>
					<?php echo $ui->input('search', array('name' => 'registration_email_filter', 'label' => 'E-Mail Schüler*in', 'placeholder' => 'E-Mail suchen', 'attrs' => array('data-flz-ags-registration-filter' => 'email', 'data-flz-ags-filter-mode' => 'contains'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</details>
			</th>
			<th scope="col" aria-sort="none">
				<button type="button" class="flz-ags-admin-sort-button" data-flz-ags-registration-sort="status">Status</button>
				<details class="flz-ags-admin-column-filter"><summary>Filtern</summary>
					<?php echo $ui->field(array('type' => 'select', 'name' => 'registration_status_filter', 'label' => 'Status', 'placeholder' => 'alle Status', 'options' => flz_ags_status_labels(), 'attrs' => array('data-flz-ags-registration-filter' => 'status'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</details>
			</th>
			<th scope="col" aria-sort="none">
				<button type="button" class="flz-ags-admin-sort-button" data-flz-ags-registration-sort="date">Datum</button>
				<details class="flz-ags-admin-column-filter"><summary>Filtern</summary>
					<?php echo $ui->input('date', array('name' => 'registration_date_filter', 'label' => 'Datum', 'attrs' => array('data-flz-ags-registration-filter' => 'date'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</details>
			</th>
			<th scope="col">
				<span>Aktion</span>
				<details class="flz-ags-admin-column-filter"><summary>Filtern</summary>
					<?php echo $ui->field(array('type' => 'select', 'name' => 'registration_action_filter', 'label' => 'Aktion', 'placeholder' => 'alle Aktionen', 'options' => array('withdrawable' => 'Widerruf möglich', 'none' => 'Keine Aktion'), 'attrs' => array('data-flz-ags-registration-filter' => 'action'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</details>
			</th>
		</tr>
	</thead>
	<tbody data-flz-ags-registration-items>
		<?php if (empty($registrations)) : ?>
			<tr><td colspan="8">Keine Anmeldungen gefunden.</td></tr>
		<?php endif; ?>
		<?php $registration_index = 0; ?>
		<?php foreach ($registrations as $registration) : ?>
			<?php
			$student_label = (string) $registration->student_last_name . ', ' . (string) $registration->student_first_name;
			$class_label = flz_ags_class_label((string) $registration->class_name);
			$course_label = (string) $registration->title;
			$slot_label = flz_ags_weekday_label($registration->weekday)
				. ', ' . flz_ags_format_time($registration->start_time)
				. '–' . flz_ags_format_time($registration->end_time)
				. ($registration->room ? ', ' . $registration->room : '');
			$email = (string) $registration->student_email;
			$status_key = (string) $registration->status;
			$status_label = flz_ags_status_label($status_key);
			$date_value = substr((string) $registration->created_at, 0, 10);
			$action_key = $status_key === 'active' ? 'withdrawable' : 'none';
			?>
			<tr data-flz-ags-registration-item
				data-filter-student="<?php echo esc_attr($student_label); ?>"
				data-filter-class="<?php echo esc_attr((string) $registration->class_name); ?>"
				data-filter-course="<?php echo esc_attr($course_label); ?>"
				data-filter-slot="<?php echo esc_attr($slot_label); ?>"
				data-filter-email="<?php echo esc_attr($email); ?>"
				data-filter-status="<?php echo esc_attr($status_key); ?>"
				data-filter-date="<?php echo esc_attr($date_value); ?>"
				data-filter-action="<?php echo esc_attr($action_key); ?>"
				data-sort-default="<?php echo esc_attr((string) $registration_index++); ?>"
				data-sort-student="<?php echo esc_attr($student_label); ?>"
				data-sort-class="<?php echo esc_attr($class_label); ?>"
				data-sort-course="<?php echo esc_attr($course_label); ?>"
				data-sort-slot="<?php echo esc_attr(sprintf('%d-%s', (int) $registration->weekday, (string) $registration->start_time)); ?>"
				data-sort-email="<?php echo esc_attr($email); ?>"
				data-sort-status="<?php echo esc_attr($status_label); ?>"
				data-sort-date="<?php echo esc_attr((string) $registration->created_at); ?>">
				<td><?php echo esc_html($student_label); ?></td>
				<td><?php echo esc_html($class_label); ?></td>
				<td><?php echo esc_html($course_label); ?></td>
				<td>
					<?php echo esc_html($slot_label); ?>
				</td>
				<td><?php echo esc_html($email); ?></td>
				<td><?php echo esc_html($status_label); ?></td>
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
		<?php if (!empty($registrations)) : ?>
			<tr hidden data-flz-ags-registration-no-results><td colspan="8">Keine Anmeldungen entsprechen den Filtern.</td></tr>
		<?php endif; ?>
	</tbody>
</table>
</div>
