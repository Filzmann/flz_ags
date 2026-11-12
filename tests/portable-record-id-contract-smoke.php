<?php
define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/includes/class-flz-ags-course-csv.php';
require_once dirname(__DIR__) . '/includes/class-flz-ags-registration-csv.php';
foreach (array(FLZ_AGS_Course_CSV::course_header(), FLZ_AGS_Course_CSV::slot_header(), FLZ_AGS_Registration_CSV::header()) as $header) {
	if (!in_array('Datensatz-ID', $header, true)) {
		throw new RuntimeException('Der CSV-Export besitzt keine portable Datensatz-ID.');
	}
}
echo "OK: AG CSV record IDs\n";
