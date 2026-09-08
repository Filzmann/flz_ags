<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
$class_options = $include_classes ? flz_ags_class_options() : flz_ags_grade_options();
$leader_options = array();
$category_options = array();
foreach ((array) ($courses ?? array()) as $course) {
	$leader = trim((string) ($course->leader_name ?? ''));
	$category = trim((string) ($course->category ?? ''));
	if ($leader !== '') {
		$leader_options[$leader] = $leader;
	}
	if ($category !== '') {
		$category_options[$category] = $category;
	}
}
natcasesort($leader_options);
natcasesort($category_options);
?>
<?php if (!$include_classes) : ?>
<details class="flz-ags-filter-details">
	<summary>AG-Liste filtern und sortieren</summary>
<?php endif; ?>
<div class="flz-ags-filters">
	<?php if ($include_classes) : ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'class_name', 'label' => 'Klasse', 'required' => true, 'placeholder' => '– Bitte auswählen –', 'options' => $class_options, 'attrs' => array('data-flz-ags-class-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php else : ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'class_filter', 'label' => 'Jahrgang', 'placeholder' => 'alle anzeigen', 'options' => $class_options, 'attrs' => array('data-flz-ags-class-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php endif; ?>

	<?php if (!$include_classes) : ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'category_filter', 'label' => 'Bereich', 'placeholder' => 'alle Bereiche', 'options' => $category_options, 'attrs' => array('data-flz-ags-category-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'weekday_filter', 'label' => 'Wochentag', 'placeholder' => 'alle Tage', 'options' => flz_ags_weekdays(), 'attrs' => array('data-flz-ags-weekday-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'leader_filter', 'label' => 'Dozent', 'placeholder' => 'alle Dozenten', 'options' => $leader_options, 'attrs' => array('data-flz-ags-leader-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $ui->input('search', array('name' => 'search_filter', 'label' => 'Suche', 'placeholder' => 'AG, Dozent, Bereich …', 'attrs' => array('data-flz-ags-search-input' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $ui->field(array('type' => 'select', 'name' => 'sort_filter', 'label' => 'Sortierung', 'options' => array('default' => 'Vorgabe', 'title' => 'AG-Name', 'category' => 'Bereich', 'grade' => 'Klassenstufe', 'leader' => 'Dozent', 'weekday' => 'Wochentag'), 'attrs' => array('data-flz-ags-sort-select' => true))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php endif; ?>
</div>
<?php if (!$include_classes) : ?>
</details>
<?php endif; ?>
