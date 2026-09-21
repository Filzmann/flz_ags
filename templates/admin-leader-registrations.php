<?php

defined('ABSPATH') || exit;
?>
<p class="description">Hier sehen Sie ausschließlich die Anmeldungen für Ihre zugewiesenen AGs. Änderungen, Exporte und Importe sind nicht möglich.</p>
<h2>Meine AGs</h2>
<?php if (empty($courses)) : ?>
    <p>Für dieses Schuljahr sind Ihnen keine AGs zugeordnet.</p>
<?php else : ?>
    <ul>
        <?php foreach ($courses as $course) : ?>
            <li><?php echo esc_html((string) $course->title); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<h2>Anmeldungen</h2>
<table class="widefat striped">
    <thead>
        <tr>
            <th scope="col">Schüler*in</th>
            <th scope="col">Klasse</th>
            <th scope="col">AG</th>
            <th scope="col">Termin</th>
            <th scope="col">E-Mail</th>
            <th scope="col">Status</th>
            <th scope="col">Angemeldet am</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($registrations)) : ?>
            <tr><td colspan="7">Für dieses Schuljahr sind Ihnen keine AG-Anmeldungen zugeordnet.</td></tr>
        <?php endif; ?>
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
