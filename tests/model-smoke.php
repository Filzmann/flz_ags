<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/tmp/' );
define( 'OBJECT', 'OBJECT' );

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

function flz_ags_status_labels(): array {
	return array(
		'active' => 'aktiv',
		'withdrawn' => 'widerrufen',
	);
}

require_once dirname( __DIR__, 2 ) . '/flz_wpdb_objects/FlzWpdbObject.php';
require_once dirname( __DIR__ ) . '/includes/models/class-flz-ags-model.php';
require_once dirname( __DIR__ ) . '/includes/models/class-flz-ags-course.php';
require_once dirname( __DIR__ ) . '/includes/models/class-flz-ags-slot.php';
require_once dirname( __DIR__ ) . '/includes/models/class-flz-ags-registration.php';

final class FlzAgsFakeWpdb {
	public string $prefix = 'wp_';
	public string $last_error = '';
	public int $insert_id = 0;
	public array $results = array();
	public string $last_query = '';

	public function prepare( string $query, ...$values ): string {
		return $query;
	}

	public function get_results( string $query, string $output = OBJECT ): array {
		$this->last_query = $query;
		return $this->results;
	}

	public function query( string $query ): int {
		$this->last_query = $query;
		return 1;
	}

	public function insert( string $table, array $data ): int {
		$this->last_query = 'INSERT ' . $table;
		$this->insert_id = 23;
		return 1;
	}

	public function update( string $table, array $data, array $where ): int {
		$this->last_query = 'UPDATE ' . $table;
		return 1;
	}
}

function flz_ags_test_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Test fehlgeschlagen: ' . $message );
	}
}

function flz_ags_test_table_name( string $class_name ): string {
	$method = new ReflectionMethod( $class_name, 'table_name' );
	$method->setAccessible( true );
	return $method->invoke( null );
}

$wpdb = new FlzAgsFakeWpdb();
$GLOBALS['wpdb'] = $wpdb;

flz_ags_test_check(
	flz_ags_test_table_name( FLZ_AGS_Course::class ) === 'wp_flz_ags_courses',
	'Der Tabellenname des AG-Modells wird nicht vom Modellnamen abgeleitet.'
);
flz_ags_test_check(
	flz_ags_test_table_name( FLZ_AGS_Slot::class ) === 'wp_flz_ags_slots',
	'Der Tabellenname des Terminmodells wird nicht vom Modellnamen abgeleitet.'
);
flz_ags_test_check(
	flz_ags_test_table_name( FLZ_AGS_Registration::class ) === 'wp_flz_ags_registrations',
	'Der Tabellenname des Anmeldungsmodells wird nicht vom Modellnamen abgeleitet.'
);

$wpdb->results = array(
	(object) array(
		'id' => '5',
		'school_year' => '2026/2027',
		'title' => 'Test-AG',
		'slug' => 'test-ag',
		'created_at' => '2026-06-24 12:00:00',
		'updated_at' => '2026-06-24 12:00:00',
	),
);
$courses = FLZ_AGS_Course::find_for_school_year( '2026/2027' );
flz_ags_test_check(
	count( $courses ) === 1 && $courses[0] instanceof FLZ_AGS_Course && $courses[0]->title === 'Test-AG',
	'Die AG-Abfrage wurde nicht als Modell hydriert.'
);
flz_ags_test_check(
	str_contains( $wpdb->last_query, 'FROM wp_flz_ags_courses' ),
	'Die AG-Abfrage verwendet nicht die abgeleitete Tabelle.'
);

$wpdb->results = array();
FLZ_AGS_Slot::find_public_for_school_year( '2026/2027' );
flz_ags_test_check(
	str_contains( $wpdb->last_query, 'FROM wp_flz_ags_slots' )
	&& str_contains( $wpdb->last_query, 'INNER JOIN wp_flz_ags_courses' ),
	'Die öffentliche Terminabfrage verwendet nicht die abgeleiteten Tabellen.'
);

$course = new FLZ_AGS_Course(
	array(
		'school_year' => '2026/2027',
		'title' => 'Neue AG',
		'slug' => 'neue-ag',
		'created_at' => '2026-06-24 12:00:00',
		'updated_at' => '2026-06-24 12:00:00',
	)
);
$course->save();
flz_ags_test_check( $course->id === 23, 'Das AG-Modell hat die Insert-ID nicht übernommen.' );
flz_ags_test_check( $wpdb->last_query === 'INSERT wp_flz_ags_courses', 'Das AG-Modell speichert in die falsche Tabelle.' );

echo "OK: flz_ags model smoke test\n";
