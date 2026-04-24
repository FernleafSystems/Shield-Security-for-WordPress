<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\{
	BuildAttentionItems,
	BuildLatestCompletedScanTimestamps,
	BuildOverview,
	BuildRecentActivity,
	BuildScanFindings,
	BuildScanRuntime
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQueryCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SiteQueryConTest extends BaseUnitTest {

	public function test_repeated_queries_hit_each_builder_once_until_cleared() :void {
		$queryCon = new SiteQueryConMemoizationTestDouble();

		$this->assertSame( $queryCon->attention(), $queryCon->attention() );
		$this->assertSame( $queryCon->overview(), $queryCon->overview() );
		$this->assertSame( $queryCon->latestCompletedScanTimestamps(), $queryCon->latestCompletedScanTimestamps() );
		$this->assertSame( $queryCon->recentActivity(), $queryCon->recentActivity() );
		$this->assertSame( $queryCon->scanRuntime(), $queryCon->scanRuntime() );
		$this->assertSame( $queryCon->scanFindings( [ 'afs' ], [ 'active' ] ), $queryCon->scanFindings( [ 'afs' ], [ 'active' ] ) );

		$this->assertSame( [
			'attention'      => 1,
			'overview'       => 1,
			'latest'         => 1,
			'recentActivity' => 1,
			'scanRuntime'    => 1,
			'scanFindings'   => 1,
		], $queryCon->calls );

		$queryCon->clearMemoized();
		$queryCon->attention();
		$queryCon->overview();
		$queryCon->latestCompletedScanTimestamps();
		$queryCon->recentActivity();
		$queryCon->scanRuntime();
		$queryCon->scanFindings( [ 'afs' ], [ 'active' ] );

		$this->assertSame( [
			'attention'      => 2,
			'overview'       => 2,
			'latest'         => 2,
			'recentActivity' => 2,
			'scanRuntime'    => 2,
			'scanFindings'   => 2,
		], $queryCon->calls );
	}

	public function test_scan_findings_cache_is_scoped_by_arguments() :void {
		$queryCon = new SiteQueryConMemoizationTestDouble();

		$queryCon->scanFindings( [ 'afs' ], [ 'active' ] );
		$queryCon->scanFindings( [ 'wpv' ], [ 'active' ] );
		$queryCon->scanFindings( [ 'afs' ], [ 'active' ] );

		$this->assertSame( 2, $queryCon->calls[ 'scanFindings' ] );
	}
}

class SiteQueryConMemoizationTestDouble extends SiteQueryCon {

	/**
	 * @var array<string,int>
	 */
	public array $calls = [
		'attention'      => 0,
		'overview'       => 0,
		'latest'         => 0,
		'recentActivity' => 0,
		'scanRuntime'    => 0,
		'scanFindings'   => 0,
	];

	protected function buildAttentionItems() :BuildAttentionItems {
		return new class( $this ) extends BuildAttentionItems {
			private SiteQueryConMemoizationTestDouble $owner;

			public function __construct( SiteQueryConMemoizationTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build() :array {
				++$this->owner->calls[ 'attention' ];
				return [
					'generated_at' => 1,
					'summary'      => [
						'total'        => 0,
						'severity'     => 'good',
						'is_all_clear' => true,
					],
					'items'        => [],
					'groups'       => [
						'scans'       => [ 'zone' => 'scans', 'total' => 0, 'severity' => 'good', 'items' => [] ],
						'maintenance' => [ 'zone' => 'maintenance', 'total' => 0, 'severity' => 'good', 'items' => [] ],
					],
				];
			}
		};
	}

	protected function buildOverview() :BuildOverview {
		return new class( $this ) extends BuildOverview {
			private SiteQueryConMemoizationTestDouble $owner;

			public function __construct( SiteQueryConMemoizationTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build() :array {
				++$this->owner->calls[ 'overview' ];
				return [
					'generated_at'      => 1,
					'site'              => [
						'url'            => 'https://example.test',
						'name'           => 'Example',
						'shield_version' => '1.0.0',
						'is_premium'     => false,
					],
					'attention_summary' => [
						'total'        => 0,
						'severity'     => 'good',
						'is_all_clear' => true,
					],
					'posture'           => [
						'severity'   => 'good',
						'percentage' => 100,
						'controls'   => [ 'total' => 0, 'good' => 0, 'warning' => 0, 'critical' => 0 ],
						'zones'      => [ 'total' => 0, 'good' => 0, 'warning' => 0, 'critical' => 0 ],
					],
					'scans'             => [
						'is_running'          => false,
						'enqueued_count'      => 0,
						'latest_completed_at' => [],
					],
				];
			}
		};
	}

	protected function buildLatestCompletedScanTimestamps() :BuildLatestCompletedScanTimestamps {
		return new class( $this ) extends BuildLatestCompletedScanTimestamps {
			private SiteQueryConMemoizationTestDouble $owner;

			public function __construct( SiteQueryConMemoizationTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build() :array {
				++$this->owner->calls[ 'latest' ];
				return [
					'malware'         => 0,
					'vulnerabilities' => 0,
					'abandoned'       => 0,
					'core_files'      => 0,
					'plugin_files'    => 0,
					'theme_files'     => 0,
				];
			}
		};
	}

	protected function buildRecentActivity() :BuildRecentActivity {
		return new class( $this ) extends BuildRecentActivity {
			private SiteQueryConMemoizationTestDouble $owner;

			public function __construct( SiteQueryConMemoizationTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build() :array {
				++$this->owner->calls[ 'recentActivity' ];
				return [
					'generated_at' => 1,
					'items'        => [],
				];
			}
		};
	}

	protected function buildScanRuntime() :BuildScanRuntime {
		return new class( $this ) extends BuildScanRuntime {
			private SiteQueryConMemoizationTestDouble $owner;

			public function __construct( SiteQueryConMemoizationTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build() :array {
				++$this->owner->calls[ 'scanRuntime' ];
				return [
					'is_running'     => false,
					'enqueued_count' => 0,
					'running_states' => [],
					'current_slug'   => '',
					'current_name'   => '',
					'progress'       => 0.0,
				];
			}
		};
	}

	protected function buildScanFindings() :BuildScanFindings {
		return new class( $this ) extends BuildScanFindings {
			private SiteQueryConMemoizationTestDouble $owner;

			public function __construct( SiteQueryConMemoizationTestDouble $owner ) {
				$this->owner = $owner;
			}

			public function build( array $scanSlugs = [], array $statesToInclude = [] ) :array {
				++$this->owner->calls[ 'scanFindings' ];
				return [
					'generated_at'       => 1,
					'is_scan_incomplete' => false,
					'filters'            => [
						'scan_slugs'        => $scanSlugs,
						'states_to_include' => $statesToInclude,
					],
					'results'            => [],
				];
			}
		};
	}
}
