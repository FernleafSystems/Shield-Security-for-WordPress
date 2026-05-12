<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	BuildConfigurationCoverage,
	ConfigureZoneTilesBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BuildConfigurationCoverageTest extends BaseUnitTest {

	public function test_build_excludes_neutral_rows_and_force_neutral_tiles_from_coverage_math() :void {
		$coverage = ( new BuildConfigurationCoverage(
			new class extends ConfigureZoneTilesBuilder {
				public function build() :array {
					return [
						[
							'key'                => 'critical_zone',
							'include_in_posture' => true,
							'status'             => 'critical',
							'panel'              => [
								'rows' => [
									[ 'status' => 'critical' ],
									[ 'status' => 'neutral' ],
								],
							],
						],
						[
							'key'                => 'warning_zone',
							'include_in_posture' => true,
							'status'             => 'warning',
							'panel'              => [
								'rows' => [
									[ 'status' => 'warning' ],
									[ 'status' => 'good' ],
									[ 'status' => 'neutral' ],
								],
							],
						],
						[
							'key'                => 'good_zone',
							'include_in_posture' => true,
							'status'             => 'good',
							'panel'              => [
								'rows' => [
									[ 'status' => 'good' ],
								],
							],
						],
						[
							'key'                => 'general',
							'include_in_posture' => false,
							'status'             => 'neutral',
							'panel'              => [
								'rows' => [
									[ 'status' => 'neutral' ],
									[ 'status' => 'critical' ],
								],
							],
						],
					];
				}
			}
		) )->build();

		$this->assertSame( 'critical', $coverage[ 'severity' ] );
		$this->assertSame( 63, $coverage[ 'percentage' ] );
		$this->assertSame( [
			'total'    => 4,
			'good'     => 2,
			'warning'  => 1,
			'critical' => 1,
		], $coverage[ 'controls' ] );
		$this->assertSame( [
			'total'    => 3,
			'good'     => 1,
			'warning'  => 1,
			'critical' => 1,
		], $coverage[ 'zones' ] );
	}

	public function test_build_defaults_to_full_coverage_when_no_eligible_rows_exist() :void {
		$coverage = ( new BuildConfigurationCoverage(
			new class extends ConfigureZoneTilesBuilder {
				public function build() :array {
					return [
						[
							'key'                => 'headers',
							'include_in_posture' => true,
							'status'             => 'good',
							'panel'              => [
								'rows' => [
									[ 'status' => 'neutral' ],
								],
							],
						],
					];
				}
			}
		) )->build();

		$this->assertSame( 'good', $coverage[ 'severity' ] );
		$this->assertSame( 100, $coverage[ 'percentage' ] );
		$this->assertSame( [
			'total'    => 0,
			'good'     => 0,
			'warning'  => 0,
			'critical' => 0,
		], $coverage[ 'controls' ] );
		$this->assertSame( [
			'total'    => 1,
			'good'     => 1,
			'warning'  => 0,
			'critical' => 0,
		], $coverage[ 'zones' ] );
	}
}
