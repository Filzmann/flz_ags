<?php

defined('ABSPATH') || exit;
?>
<div class="flz-ags flz-ags-list" data-flz-ags-list>
	<h2>Arbeitsgemeinschaften <?php echo esc_html($school_year); ?></h2>
	<?php flz_ags_render_frontend_filters(false); ?>

	<?php if (empty($courses)) : ?>
		<p>Derzeit sind keine AGs für dieses Schuljahr veröffentlicht.</p>
	<?php else : ?>
		<div class="flz-ags-grid">
			<?php foreach ($courses as $course) : ?>
				<?php flz_ags_render_course_card($course); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
