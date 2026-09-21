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
$course_edit_options = array();
$slot_edit_options_by_course = array();
foreach ((array) $slots as $slot_option) {
	$taken = FLZ_AGS_Registration::count_by(array('slot_id' => (int) $slot_option->id, 'status' => 'active'));
	$max_participants = (int) ($slot_option->max_participants ?? 0);
	if ($max_participants > 0 && $taken >= $max_participants) {
		continue;
	}

	$course_id = (int) ($slot_option->course_id ?? 0);
	$course_title = (string) ($slot_option->title ?? '');
	$slot_label = (string) ($slot_option->title ?? '') . ' · '
		. flz_ags_weekday_label((int) ($slot_option->weekday ?? 0))
		. ', ' . flz_ags_format_time((string) ($slot_option->start_time ?? ''))
		. '–' . flz_ags_format_time((string) ($slot_option->end_time ?? ''))
		. (!empty($slot_option->room) ? ', ' . (string) $slot_option->room : '');
	if ($course_id > 0 && $course_title !== '' && !empty($slot_option->id)) {
		$course_edit_options[$course_id] = $course_title;
		$slot_edit_options_by_course[$course_id][(int) $slot_option->id] = $slot_label;
	}
}
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
					<?php echo $ui->field(array('type' => 'select', 'name' => 'registration_action_filter', 'label' => 'Aktion', 'placeholder' => 'alle Aktionen', 'options' => array('editable' => 'Bearbeitbar'), 'attrs' => array('data-flz-ags-registration-filter' => 'action'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
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
			$action_key = 'editable';
			$current_course_id = (int) $registration->course_id;
			$row_course_edit_options = $course_edit_options;
			$row_slot_edit_options_by_course = $slot_edit_options_by_course;
			if (!isset($row_course_edit_options[$current_course_id])) {
				$row_course_edit_options[$current_course_id] = $course_label;
			}
			if (!isset($row_slot_edit_options_by_course[$current_course_id][(int) $registration->slot_id])) {
				$row_slot_edit_options_by_course[$current_course_id][(int) $registration->slot_id] = $course_label . ' · ' . $slot_label;
			}
			?>
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui rendert die vollständige, escapte Inline-Bearbeitungszeile.
			echo $ui->editable_row(array(
				'id' => 'flz-ags-registration-' . absint($registration->id),
				'attrs' => array(
					'data-flz-ags-registration-item' => true,
					'data-filter-student' => $student_label,
					'data-filter-class' => (string) $registration->class_name,
					'data-filter-course' => $course_label,
					'data-filter-slot' => $slot_label,
					'data-filter-email' => $email,
					'data-filter-status' => $status_key,
					'data-filter-date' => $date_value,
					'data-filter-action' => $action_key,
					'data-sort-default' => (string) $registration_index++,
					'data-sort-student' => $student_label,
					'data-sort-class' => $class_label,
					'data-sort-course' => $course_label,
					'data-sort-slot' => sprintf('%d-%s', (int) $registration->weekday, (string) $registration->start_time),
					'data-sort-email' => $email,
					'data-sort-status' => $status_label,
					'data-sort-date' => (string) $registration->created_at,
				),
				'form' => array(
					'method' => 'post',
					'action' => esc_url(admin_url('admin-post.php')),
					'nonce' => 'flz_ags_update_registration',
					'hidden' => array(
						'action' => 'flz_ags_update_registration',
						'registration_id' => absint($registration->id),
					),
				),
				'cells' => array(
					array('view' => $student_label, 'field' => array('type' => 'text', 'name' => 'student_last_name', 'label' => 'Nachname', 'value' => (string) $registration->student_last_name, 'required' => true)),
					array('view' => $class_label, 'field' => array('type' => 'text', 'name' => 'class_name', 'label' => 'Klasse', 'value' => (string) $registration->class_name, 'required' => true)),
					array('view' => $course_label, 'field' => array('type' => 'select', 'name' => 'course_id', 'label' => 'AG', 'value' => $current_course_id, 'options' => $row_course_edit_options, 'attrs' => array('data-flz-ags-registration-course' => true))),
					array('view' => $slot_label, 'field' => array('type' => 'select', 'name' => 'slot_id', 'label' => 'Slot', 'value' => (int) $registration->slot_id, 'options' => $row_slot_edit_options_by_course[$current_course_id], 'required' => true, 'attrs' => array('data-flz-ags-registration-slot' => true, 'data-flz-ags-registration-slot-map' => wp_json_encode($row_slot_edit_options_by_course)))),
					array('view' => $email, 'field' => array('type' => 'email', 'name' => 'student_email', 'label' => 'E-Mail Schüler*in', 'value' => $email, 'required' => true, 'autocomplete' => 'email')),
					array('view' => $status_label, 'field' => array('type' => 'select', 'name' => 'new_status', 'label' => 'Status', 'value' => $status_key, 'options' => flz_ags_status_labels())),
					array('view' => flz_ui_format_datetime($registration->created_at, (string) $registration->created_at)),
				),
				'edit' => array('label' => 'Anmeldung bearbeiten'),
				'save' => array('label' => 'Anmeldung speichern'),
				'details' => array(
					'label' => 'Weitere Angaben',
					'fields' => array(
						array('type' => 'text', 'name' => 'student_first_name', 'label' => 'Vorname', 'value' => (string) $registration->student_first_name, 'required' => true),
					),
				),
			));
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<?php endforeach; ?>
		<?php if (!empty($registrations)) : ?>
			<tr hidden data-flz-ags-registration-no-results><td colspan="8">Keine Anmeldungen entsprechen den Filtern.</td></tr>
		<?php endif; ?>
	</tbody>
</table>
</div>
