<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\{
	BuildAttentionItems,
	BuildLatestCompletedScanTimestamps,
	BuildOverview,
	BuildScanRuntime
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQueryCon;
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
		$this->assertSame( [
			'severity'   => 'warning',
			'percentage' => 81,
			'controls'   => [
				'total'    => 8,
				'good'     => 5,
				'warning'  => 2,
				'critical' => 1,
			],
			'zones'      => [
				'total'    => 3,
				'good'     => 1,
				'warning'  => 1,
				'critical' => 1,
			],
		], $overview[ 'posture' ] );
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

	public function test_build_reuses_site_query_contract_cache_for_subqueries() :void {
		$siteQuery = new BuildOverviewSiteQueryConTestDouble();
		$siteQuery->attention();

		$overview = ( new BuildOverviewUsesSiteQueryConTestDouble( $siteQuery ) )->build();

		$this->assertSame( 1, $siteQuery->calls[ 'attention' ] );
		$this->assertSame( 1, $siteQuery->calls[ 'runtime' ] );
		$this->assertSame( 1, $siteQuery->calls[ 'latest' ] );
		$this->assertSame( 4, $overview[ 'attention_summary' ][ 'total' ] );
		$this->assertSame( 2, $overview[ 'scans' ][ 'enqueued_count' ] );
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
			'severity'   => 'warning',
			'percentage' => 81,
			'controls'   => [
				'total'    => 8,
				'good'     => 5,
				'warning'  => 2,
				'critical' => 1,
			],
			'zones'      => [
				'total'    => 3,
				'good'     => 1,
				'warning'  => 1,
				'critical' => 1,
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

	protected function buildLatestCompletedScanTimestamps() :array {
		return [
			'malware'         => 111,
			'vulnerabilities' => 222,
			'abandoned'       => 333,
			'core_files'      => 111,
			'plugin_files'    => 111,
			'theme_files'     => 111,
		];
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

class BuildOverviewUsesSiteQueryConTestDouble extends BuildOverview {

	private SiteQueryCon $siteQueryCon;

	public function __construct( SiteQueryCon $siteQueryCon ) {
		$this->siteQueryCon = $siteQueryCon;
	}

	protected function buildPosture() :array {
		return [
			'severity'   => 'good',
			'percentage' => 100,
			'controls'   => [
				'total'    => 1,
				'good'     => 1,
				'warning'  => 0,
				'critical' => 0,
			],
			'zones'      => [
				'total'    => 1,
				'good'     => 1,
				'warning'  => 0,
				'critical' => 0,
			],
		];
	}

	protected function buildSite() :array {
		return [
			'url'            => 'https://example.com',
			'name'           => 'Example Site',
			'shield_version' => '18.2.1',
			'is_premium'     => true,
		];
	}

	protected function siteQueryCon() :SiteQueryCon {
		return $this->siteQueryCon;
	}
}

class BuildOverviewSiteQueryConTestDouble extends SiteQueryCon {

	/**
	 * @var array<string,int>
	 */
	public array $calls = [
		'attention' => 0,
		'runtime'   => 0,
		'latest'    => 0,
	];

	protected function buildAttentionItems() :BuildAttentionItems {
		return new class( $this ) extends BuildAttentionItems {
			private BuildOverviewSiteQueryConTestDouble $owner;

			public function __construct( BuildOverviewSiteQueryConTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build() :array {
				++$this->owner->calls[ 'attention' ];
				return [
					'generated_at' => 1700000000,
					'summary'      => [
						'total'        => 4,
						'severity'     => 'critical',
						'is_all_clear' => false,
					],
					'items'        => [],
					'groups'       => [
						'scans'       => [ 'zone' => 'scans', 'total' => 4, 'severity' => 'critical', 'items' => [] ],
						'maintenance' => [ 'zone' => 'maintenance', 'total' => 0, 'severity' => 'good', 'items' => [] ],
					],
				];
			}
		};
	}

	protected function buildLatestCompletedScanTimestamps() :BuildLatestCompletedScanTimestamps {
		return new class( $this ) extends BuildLatestCompletedScanTimestamps {
			private BuildOverviewSiteQueryConTestDouble $owner;

			public function __construct( BuildOverviewSiteQueryConTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build() :array {
				++$this->owner->calls[ 'latest' ];
				return [
					'malware'         => 10,
					'vulnerabilities' => 20,
					'abandoned'       => 30,
					'core_files'      => 10,
					'plugin_files'    => 10,
					'theme_files'     => 10,
				];
			}
		};
	}

	protected function buildScanRuntime() :BuildScanRuntime {
		return new class( $this ) extends BuildScanRuntime {
			private BuildOverviewSiteQueryConTestDouble $owner;

			public function __construct( BuildOverviewSiteQueryConTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build() :array {
				++$this->owner->calls[ 'runtime' ];
				return [
					'is_running'     => true,
					'enqueued_count' => 2,
					'running_states' => [],
					'current_slug'   => 'afs',
					'current_name'   => 'File Scan',
					'progress'       => 0.5,
				];
			}
		};
	}
}
