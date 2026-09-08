<?php

defined('ABSPATH') || exit;
?>
<div class="flz-ags flz-ags-list" data-flz-ags-list>
	<h2>Arbeitsgemeinschaften <?php echo esc_html($school_year); ?></h2>
	<?php flz_ags_render_frontend_filters(false, $courses); ?>
	<p class="screen-reader-text" aria-live="polite" data-flz-ags-results-status></p>

	<?php if (empty($courses)) : ?>
		<p>Derzeit sind keine AGs für dieses Schuljahr veröffentlicht.</p>
	<?php else : ?>
		<div class="flz-ags-grid" data-flz-ags-items>
			<?php foreach ($courses as $course) : ?>
				<?php flz_ags_render_course_card($course); ?>
			<?php endforeach; ?>
		</div>
		<p data-flz-ags-no-results hidden>Keine AG entspricht den gewählten Filtern.</p>
	<?php endif; ?>
</div>
