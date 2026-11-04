<?php

defined( 'ABSPATH' ) || exit;

$ui = flz_ui();
$options = array( 'class' => array(), 'course' => array(), 'slot' => array() );
foreach ( (array) $registrations as $item ) {
	$class = (string) $item->class_name;
	$course = (string) $item->title;
	$slot = flz_ags_weekday_label( (int) $item->weekday ) . ', ' . flz_ags_format_time( (string) $item->start_time ) . '–' . flz_ags_format_time( (string) $item->end_time ) . ( $item->room ? ', ' . $item->room : '' );
	$options['class'][ $class ] = flz_ags_class_label( $class );
	$options['course'][ $course ] = $course;
	$options['slot'][ $slot ] = $slot;
}
foreach ( $options as &$column_options ) {
	natcasesort( $column_options );
}
unset( $column_options );
$columns = array(
	'student' => array( 'label' => 'Schüler*in', 'type' => 'search', 'placeholder' => 'Name suchen' ),
	'class' => array( 'label' => 'Klasse', 'type' => 'select', 'placeholder' => 'alle Klassen' ),
	'course' => array( 'label' => 'AG', 'type' => 'select', 'placeholder' => 'alle AGs' ),
	'slot' => array( 'label' => 'Termin', 'type' => 'select', 'placeholder' => 'alle Termine' ),
	'email' => array( 'label' => 'E-Mail', 'type' => 'search', 'placeholder' => 'E-Mail suchen' ),
	'status' => array( 'label' => 'Status', 'type' => 'select', 'placeholder' => 'alle Status' ),
	'date' => array( 'label' => 'Angemeldet am', 'type' => 'date', 'placeholder' => '' ),
);
?>
<div data-flz-ags-registration-list>
<p class="screen-reader-text" aria-live="polite" data-flz-ags-registration-results-status></p>
<table class="widefat striped"><thead><tr>
	<?php foreach ( $columns as $key => $column ) : ?>
		<th scope="col" aria-sort="none"><button type="button" class="flz-ags-admin-sort-button" data-flz-ags-registration-sort="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $column['label'] ); ?></button><details class="flz-ags-admin-column-filter"><summary>Filtern</summary><?php if ( $column['type'] === 'select' ) : ?><?php echo $ui->field( array( 'type' => 'select', 'name' => 'registration_' . $key . '_filter', 'label' => $column['label'], 'placeholder' => $column['placeholder'], 'options' => $key === 'status' ? flz_ags_status_labels() : $options[ $key ], 'attrs' => array( 'data-flz-ags-registration-filter' => $key ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?><?php else : ?><?php echo $ui->input( $column['type'], array( 'name' => 'registration_' . $key . '_filter', 'label' => $column['label'], 'placeholder' => $column['placeholder'], 'attrs' => array( 'data-flz-ags-registration-filter' => $key, 'data-flz-ags-filter-mode' => $column['type'] === 'search' ? 'contains' : '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?><?php endif; ?></details></th>
	<?php endforeach; ?>
</tr></thead><tbody data-flz-ags-registration-items>
	<?php if ( empty( $registrations ) ) : ?><tr><td colspan="7"><?php echo esc_html( $empty_message ); ?></td></tr><?php endif; ?>
	<?php foreach ( $registrations as $index => $registration ) : ?>
		<?php
		$values = array(
			'student' => trim( (string) $registration->student_first_name . ' ' . (string) $registration->student_last_name ),
			'class' => (string) $registration->class_name,
			'course' => (string) $registration->title,
			'slot' => flz_ags_weekday_label( (int) $registration->weekday ) . ', ' . flz_ags_format_time( (string) $registration->start_time ) . '–' . flz_ags_format_time( (string) $registration->end_time ) . ( $registration->room ? ', ' . $registration->room : '' ),
			'email' => (string) $registration->student_email,
			'status' => (string) $registration->status,
			'date' => substr( (string) $registration->created_at, 0, 10 ),
		);
		$display = $values;
		$display['class'] = flz_ags_class_label( $values['class'] );
		$display['status'] = flz_ags_status_label( $values['status'] );
		$display['date'] = flz_ui_format_datetime( $registration->created_at, (string) $registration->created_at );
		?>
		<tr data-flz-ags-registration-item <?php foreach ( $values as $key => $value ) : ?>data-filter-<?php echo esc_attr( $key ); ?>="<?php echo esc_attr( $value ); ?>" <?php endforeach; ?>data-sort-default="<?php echo esc_attr( (string) $index ); ?>" data-sort-student="<?php echo esc_attr( $values['student'] ); ?>" data-sort-class="<?php echo esc_attr( $display['class'] ); ?>" data-sort-course="<?php echo esc_attr( $values['course'] ); ?>" data-sort-slot="<?php echo esc_attr( sprintf( '%d-%s', (int) $registration->weekday, (string) $registration->start_time ) ); ?>" data-sort-email="<?php echo esc_attr( $values['email'] ); ?>" data-sort-status="<?php echo esc_attr( $display['status'] ); ?>" data-sort-date="<?php echo esc_attr( (string) $registration->created_at ); ?>">
			<?php foreach ( $columns as $key => $column ) : ?><td><?php echo esc_html( $display[ $key ] ); ?></td><?php endforeach; ?>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! empty( $registrations ) ) : ?><tr hidden data-flz-ags-registration-no-results><td colspan="7">Keine Anmeldungen entsprechen den Filtern.</td></tr><?php endif; ?>
</tbody></table>
</div>
