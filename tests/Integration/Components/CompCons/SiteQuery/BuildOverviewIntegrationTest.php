<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZonePosture;
use FernleafSystems\Wordpress\Services\Services;

class BuildOverviewIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_items' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->loginAsSecurityAdmin();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	public function test_overview_uses_live_site_query_runtime_attention_and_latest_completed_mappings() :void {
		$this->setPluginUpdateAvailable();

		$timestamps = [
			'afs' => \time() - 300,
			'wpv' => \time() - 200,
			'apc' => \time() - 100,
		];
		TestDataFactory::insertCompletedScan( 'afs', $timestamps[ 'afs' ] );
		TestDataFactory::insertCompletedScan( 'wpv', $timestamps[ 'wpv' ] );
		TestDataFactory::insertCompletedScan( 'apc', $timestamps[ 'apc' ] );

		$overview = self::con()->comps->site_query->overview();
		$attention = self::con()->comps->site_query->attention();
		$runtime = self::con()->comps->site_query->scanRuntime();
		$posture = ( new BuildZonePosture() )->build();

		$this->assertSame( Services::WpGeneral()->getHomeUrl(), $overview[ 'site' ][ 'url' ] );
		$this->assertSame( Services::WpGeneral()->getSiteName(), $overview[ 'site' ][ 'name' ] );
		$this->assertSame( self::con()->cfg->version(), $overview[ 'site' ][ 'shield_version' ] );
		$this->assertSame( self::con()->isPremiumActive(), $overview[ 'site' ][ 'is_premium' ] );

		$this->assertSame( $attention[ 'summary' ], $overview[ 'attention_summary' ] );
		$this->assertSame( [
			'status'     => $posture[ 'status' ],
			'severity'   => $posture[ 'severity' ],
			'percentage' => $posture[ 'percentage' ],
			'totals'     => $posture[ 'totals' ],
		], $overview[ 'posture' ] );
		$this->assertSame( $runtime[ 'is_running' ], $overview[ 'scans' ][ 'is_running' ] );
		$this->assertSame( $runtime[ 'enqueued_count' ], $overview[ 'scans' ][ 'enqueued_count' ] );

		$this->assertSame( [
			'malware'         => $timestamps[ 'afs' ],
			'vulnerabilities' => $timestamps[ 'wpv' ],
			'abandoned'       => $timestamps[ 'apc' ],
			'core_files'      => $timestamps[ 'afs' ],
			'plugin_files'    => $timestamps[ 'afs' ],
			'theme_files'     => $timestamps[ 'afs' ],
		], $overview[ 'scans' ][ 'latest_completed_at' ] );
	}

	private function setPluginUpdateAvailable() :void {
		$updates = new \stdClass();
		$updates->response = [
			self::con()->base_file => (object)[
				'plugin'      => self::con()->base_file,
				'new_version' => self::con()->cfg->version().'.1',
			],
		];
		\set_site_transient( 'update_plugins', $updates );
	}
}
