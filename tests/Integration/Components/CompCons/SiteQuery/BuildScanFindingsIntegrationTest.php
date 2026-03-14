<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class BuildScanFindingsIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_items' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->loginAsSecurityAdmin();
	}

	public function test_scan_findings_returns_live_results_and_filter_normalization() :void {
		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => 'plugin-vulnerable',
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => 'plugin-abandoned',
			'is_abandoned' => 1,
		] );

		$query = self::con()->comps->site_query->scanFindings( [ 'wpv', 'apc' ], [ 'is_vulnerable' ] );

		$this->assertTrue( $query[ 'is_available' ] );
		$this->assertSame( '', $query[ 'message' ] );
		$this->assertSame( [ 'wpv', 'apc' ], $query[ 'filters' ][ 'scan_slugs' ] );
		$this->assertSame( [ 'is_vulnerable' ], $query[ 'filters' ][ 'states' ] );
		$this->assertSame( 1, $query[ 'results' ][ 'wpv' ][ 'total' ] );
		$this->assertSame( 0, $query[ 'results' ][ 'apc' ][ 'total' ] );
		$this->assertSame( [
			'item_id'    => 'plugin-vulnerable',
			'states'     => [ 'is_vulnerable' ],
			'is_ignored' => false,
		], $query[ 'results' ][ 'wpv' ][ 'items' ][ 0 ] );
	}

	public function test_scan_findings_does_not_duplicate_items_when_multiple_states_match() :void {
		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => 'plugin-multi-state',
			'is_vulnerable' => 1,
			'is_abandoned'  => 1,
		] );

		$query = self::con()->comps->site_query->scanFindings( [ 'wpv' ], [ 'is_vulnerable', 'is_abandoned' ] );

		$this->assertTrue( $query[ 'is_available' ] );
		$this->assertSame( 1, $query[ 'results' ][ 'wpv' ][ 'total' ] );
		$this->assertCount( 1, $query[ 'results' ][ 'wpv' ][ 'items' ] );
		$this->assertSame( 'plugin-multi-state', $query[ 'results' ][ 'wpv' ][ 'items' ][ 0 ][ 'item_id' ] );
		$this->assertSame( [ 'is_abandoned', 'is_vulnerable' ], $query[ 'results' ][ 'wpv' ][ 'items' ][ 0 ][ 'states' ] );
	}

	public function test_scan_findings_returns_hardened_minimal_shape_for_file_findings() :void {
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'         => 'wp-admin/admin.php',
			'path_fragment'   => 'wp-admin/admin.php',
			'checksum_sha256' => 'secret-checksum',
			'malware_record_id' => 99,
			'is_checksumfail' => 1,
			'is_in_core'      => 1,
		] );

		$query = self::con()->comps->site_query->scanFindings( [ 'afs' ], [ 'is_checksumfail' ] );
		$item = $query[ 'results' ][ 'afs' ][ 'items' ][ 0 ];

		$this->assertSame( [
			'item_id'    => 'wp-admin/admin.php',
			'states'     => [ 'is_checksumfail' ],
			'is_ignored' => false,
			'scope'      => 'core',
		], $item );
		$this->assertArrayNotHasKey( 'path_fragment', $item );
		$this->assertArrayNotHasKey( 'path_full', $item );
		$this->assertArrayNotHasKey( 'checksum_sha256', $item );
		$this->assertArrayNotHasKey( 'malware_record_id', $item );
	}
}
