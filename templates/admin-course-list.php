<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
?>
<p>
	<?php echo $ui->button_new(array('href' => flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'new')), 'label' => 'Neue AG anlegen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $ui->button_view(array('href' => flz_ags_admin_url(array('page' => 'flz-ags-demo')), 'label' => 'Demo-Setup anzeigen')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
</p>

<?php echo $ui->form_start(array('method' => 'get', 'class' => 'flz-ags-admin-filter', 'hidden' => array('page' => 'flz-ags'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<?php echo $ui->input('text', array('name' => 'school_year', 'label' => 'Schuljahr', 'value' => $school_year, 'placeholder' => '2026/2027', 'class' => 'flz-ui-field--inline')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $ui->button_filter(array('label' => 'AG-Liste filtern')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

<section class="flz-ui-panel flz-ui-csv-panel">
	<h3 class="flz-ui-panel__title">AGs und Termine als CSV</h3>
	<p class="flz-ui-panel__description">CSV herunterladen oder direkt hochladen. Beim Import werden vorhandene Slot-Zeilen automatisch erkannt. Fehlende optionale Werte erhalten sichere Standardwerte; fehlerhafte Einzelzeilen werden anschließend mit Zeilennummer erklärt.</p>
	<p class="flz-ui-csv-panel__format"><strong>Format:</strong> <code>format_version; record_type; course_key; school_year; course_*; slot_*</code></p>
	<div class="flz-ui-csv-panel__actions">
		<?php
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped Formulare, Nonces und Felder vollständig.
		echo $ui->form_start(array(
			'method' => 'get',
			'action' => esc_url(admin_url('admin-post.php')),
			'nonce'  => 'flz_ags_export_courses_csv',
			'hidden' => array(
				'action'      => 'flz_ags_export_courses_csv',
				'school_year' => $school_year,
			),
		));
		echo $ui->field(array(
			'type'    => 'checkbox',
			'name' => 'include_slots',
			'label'   => 'Termine/Slots mit exportieren',
			'checked' => true,
		));
		echo $ui->button_export(array('label' => 'AG-CSV herunterladen', 'type' => 'submit'));
		echo $ui->form_end();

		echo $ui->form_start(array(
			'method'  => 'post',
			'action'  => esc_url(admin_url('admin-post.php')),
			'enctype' => 'multipart/form-data',
			'nonce'   => 'flz_ags_import_courses_csv',
			'hidden'  => array(
				'action'      => 'flz_ags_import_courses_csv',
				'school_year' => $school_year,
			),
		));
		echo $ui->input('file', array(
			'name'   => 'course-csv',
			'id'     => 'flz-ags-course-csv-import',
			'label'  => 'AG-CSV-Datei',
			'accept' => '.csv',
		));
		echo $ui->field(array(
			'type'    => 'radio',
			'name' => 'school_year_mode',
			'label'   => 'Schuljahr beim Import',
			'value'   => 'preserve',
			'options' => array(
				'preserve' => 'Schuljahre aus CSV behalten',
				'replace'  => 'Alle AGs in folgendes Schuljahr importieren',
			),
		));
		echo $ui->field(array(
			'type'        => 'select',
			'name'        => 'target_school_year',
			'label'       => 'Zielschuljahr',
			'value'       => $target_school_year,
			'options'     => $school_year_options,
			'description' => 'Wird nur verwendet, wenn alle AGs in ein gemeinsames Schuljahr importiert werden sollen.',
		));
		echo $ui->button_upload(array(
			'label'   => 'AG-CSV hochladen',
			'confirm' => 'CSV jetzt importieren? Brauchbare Datensätze werden sofort übernommen.',
			'attrs'   => array('name' => 'submit_csv'),
		));
		echo $ui->form_end();
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>
</section>

<?php $csv_warnings = is_array($csv_report) ? (array) ($csv_report['warnings'] ?? array()) : array(); ?>
<?php if (!empty($csv_warnings)) : ?>
	<section class="notice notice-warning inline flz-ags-csv-report" aria-labelledby="flz-ags-csv-report-title">
		<h3 id="flz-ags-csv-report-title">Hinweise zum letzten CSV-Import</h3>
		<p>Die übrigen Datensätze wurden verarbeitet. Folgende Angaben wurden automatisch korrigiert oder einzeln ausgelassen:</p>
		<ul>
			<?php foreach ($csv_warnings as $csv_warning) : ?>
				<li><?php echo esc_html((string) $csv_warning); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<table class="widefat striped">
	<thead>
		<tr>
			<th>Bild</th>
			<th>AG</th>
			<th>Bereich</th>
			<th>Zielgruppe</th>
			<th>Slots</th>
			<th>Status</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
	<?php if (empty($courses)) : ?>
		<tr><td colspan="7">Keine AGs für dieses Schuljahr angelegt.</td></tr>
	<?php endif; ?>
	<?php foreach ($courses as $course) : ?>
		<?php
		$slots = FLZ_AGS_Slot::find_for_course((int) $course->id, false);
		$slot_labels = array();
		foreach ($slots as $slot) {
			$slot_labels[] = flz_ags_weekday_label($slot->weekday) . ', ' . flz_ags_format_time($slot->start_time) . '–' . flz_ags_format_time($slot->end_time) . ($slot->room ? ', ' . $slot->room : '');
		}
		$target = $course->only_grade_7 ? 'nur Klasse 7' : flz_ags_allowed_grades_label((string) $course->allowed_grades);
		$status = array(
			$course->is_active ? 'aktiv' : 'inaktiv',
			$course->is_visible ? 'sichtbar' : 'versteckt',
			$course->registration_open ? 'Anmeldung offen' : 'Anmeldung geschlossen',
		);
		?>
		<tr>
			<td><img class="flz-ags-admin-thumb" src="<?php echo esc_url(flz_ags_course_image_url($course->image_url ?? '')); ?>" alt=""></td>
			<td><strong><?php echo esc_html($course->title); ?></strong><br><small><?php echo esc_html($course->school_year); ?></small></td>
			<td><?php echo esc_html((string) $course->category); ?></td>
			<td><?php echo esc_html($target); ?></td>
			<td><?php echo esc_html(implode(' | ', $slot_labels)); ?></td>
			<td><?php echo esc_html(implode(', ', $status)); ?></td>
			<td>
				<?php echo $ui->button_edit(array('href' => flz_ags_admin_url(array('page' => 'flz-ags', 'action' => 'edit', 'course_id' => (int) $course->id)), 'label' => 'AG bearbeiten')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
