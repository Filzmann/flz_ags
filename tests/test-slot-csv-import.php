<?php
/**
 * WordPress-Integrationstest für den separaten Termin-CSV-Import.
 *
 * @package Flz_ags
 */

class FLZ_AGS_Slot_CSV_Import_Test extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		FLZ_AGS_Slot::truncate_table();
		FLZ_AGS_Course::truncate_table();
	}

	public function test_existing_course_keeps_its_metadata_and_receives_imported_slots(): void {
		$course = $this->create_course( 'Robotics AG' );
		$key    = FLZ_AGS_Course_CSV::course_key( '2026/2027', 'robotics-ag' );

		$result = $this->import_slots(
			array(
				$key => array(
					'school_year' => '2026/2027',
					'title'       => 'Robotics AG',
					'slots'       => array(
						array(
							'weekday'          => 2,
							'start_time'       => '14:30',
							'end_time'         => '16:00',
							'room'             => 'A 101',
							'max_participants' => 18,
							'is_active'        => 1,
							'sort_order'       => 3,
						),
					),
				),
			)
		);

		$this->assertSame( array(), $result['warnings'] );
		$this->assertSame( 'Robotics AG', $result['courses'][ $key ]['course']['title'] );
		$this->assertSame( 'Technik', $result['courses'][ $key ]['course']['category'] );
		$this->assertSame( 1, $result['courses'][ $key ]['course']['is_visible'] );
		$this->assertSame( 18, $result['courses'][ $key ]['slots'][0]['max_participants'] );
		$this->assertSame( $course->id, FLZ_AGS_Course::get_by_id( $course->id )->id );
	}

	public function test_unknown_course_is_denied_without_creating_courses_or_slots(): void {
		$key = FLZ_AGS_Course_CSV::course_key( '2026/2027', 'unbekannte-ag' );

		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'keine Termine für vorhandene AGs' );

		try {
			$this->import_slots(
				array(
					$key => array(
						'school_year' => '2026/2027',
						'title'       => 'Unbekannte AG',
						'slots'       => array(),
					),
				)
			);
		} finally {
			$this->assertSame( 0, FLZ_AGS_Course::count_by() );
			$this->assertSame( 0, FLZ_AGS_Slot::count_by() );
		}
	}

	private function create_course( string $title ): FLZ_AGS_Course {
		$course = new FLZ_AGS_Course(
			array(
				'school_year'       => '2026/2027',
				'title'             => $title,
				'slug'              => sanitize_title( $title ),
				'category'          => 'Technik',
				'is_active'         => 1,
				'is_visible'        => 1,
				'registration_open' => 1,
				'created_at'        => '2026-01-01 10:00:00',
				'updated_at'        => '2026-01-01 10:00:00',
			)
		);
		$course->save();

		return $course;
	}

	/** @param array<string,array{school_year:string,title:string,slots:array<int,array<string,mixed>>}> $items */
	private function import_slots( array $items ): array {
		$method = new ReflectionMethod( FLZ_AGS_Plugin::class, 'slot_csv_import_data' );
		$method->setAccessible( true );

		return $method->invoke( FLZ_AGS_Plugin::instance(), $items );
	}
}
