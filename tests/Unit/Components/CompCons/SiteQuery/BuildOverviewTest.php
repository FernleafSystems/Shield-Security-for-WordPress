<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildOverview;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Request;

class BuildOverviewTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new class extends Request {
				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_maps_site_posture_runtime_and_latest_completed_timestamps() :void {
		$overview = ( new BuildOverviewTestDouble() )->build();

		$this->assertSame( 1700000000, $overview[ 'generated_at' ] );
		$this->assertSame( 'https://example.com', $overview[ 'site' ][ 'url' ] );
		$this->assertSame( 'Example Site', $overview[ 'site' ][ 'name' ] );
		$this->assertSame( '18.2.1', $overview[ 'site' ][ 'shield_version' ] );
		$this->assertTrue( $overview[ 'site' ][ 'is_premium' ] );
		$this->assertSame( 2, $overview[ 'attention_summary' ][ 'total' ] );
		$this->assertSame( 81, $overview[ 'posture' ][ 'percentage' ] );
		$this->assertTrue( $overview[ 'scans' ][ 'is_running' ] );
		$this->assertSame( 3, $overview[ 'scans' ][ 'enqueued_count' ] );
		$this->assertSame( [
			'malware'         => 111,
			'vulnerabilities' => 222,
			'abandoned'       => 333,
			'core_files'      => 111,
			'plugin_files'    => 111,
			'theme_files'     => 111,
		], $overview[ 'scans' ][ 'latest_completed_at' ] );
	}
}

class BuildOverviewTestDouble extends BuildOverview {

	protected function buildAttentionQuery() :array {
		return [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 2,
				'severity'     => 'warning',
				'is_all_clear' => false,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [ 'zone' => 'scans', 'total' => 1, 'severity' => 'warning', 'items' => [] ],
				'maintenance' => [ 'zone' => 'maintenance', 'total' => 1, 'severity' => 'warning', 'items' => [] ],
			],
		];
	}

	protected function buildPosture() :array {
		return [
			'status'     => 'warning',
			'severity'   => 'warning',
			'percentage' => 81,
			'totals'     => [
				'score'        => 81,
				'max_weight'   => 100,
				'percentage'   => 81,
				'letter_score' => 'B',
			],
		];
	}

	protected function buildScanRuntime() :array {
		return [
			'is_running'     => true,
			'enqueued_count' => 3,
			'running_states' => [],
			'current_slug'   => 'afs',
			'current_name'   => 'File Scan',
			'progress'       => 0.5,
		];
	}

	protected function getLatestCompletedScanTimestamp( string $scanSlug ) :int {
		return [
			'afs' => 111,
			'wpv' => 222,
			'apc' => 333,
		][ $scanSlug ];
	}

	protected function buildSite() :array {
		return [
			'url'            => 'https://example.com',
			'name'           => 'Example Site',
			'shield_version' => '18.2.1',
			'is_premium'     => true,
		];
	}
}
