<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ShieldQueryRoutesIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'events' );

		$this->enablePremiumCapabilities( [ 'rest_api_level_2' ] );
		$this->loginAsAdministrator();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	public function test_posture_overview_route_returns_canonical_site_query_payload() :void {
		$this->setPluginUpdateAvailable();
		$timestamps = [
			'afs' => \time() - 300,
			'wpv' => \time() - 200,
			'apc' => \time() - 100,
		];
		TestDataFactory::insertCompletedScan( 'afs', $timestamps[ 'afs' ] );
		TestDataFactory::insertCompletedScan( 'wpv', $timestamps[ 'wpv' ] );
		TestDataFactory::insertCompletedScan( 'apc', $timestamps[ 'apc' ] );

		$response = $this->dispatchReadRoute( '/shield/v1/posture/overview' );
		$data = $this->assertSuccessfulResponse( $response );
		$expected = self::con()->comps->site_query->overview();
		$expected[ 'generated_at' ] = $data[ 'generated_at' ];

		$this->assertSame( $expected, $this->extractPayload( $data ) );
	}

	public function test_posture_attention_route_returns_documented_subset_of_attention_query() :void {
		$this->setPluginUpdateAvailable();

		$response = $this->dispatchReadRoute( '/shield/v1/posture/attention' );
		$data = $this->assertSuccessfulResponse( $response );
		$expected = self::con()->comps->site_query->attention();
		$expected[ 'generated_at' ] = $data[ 'generated_at' ];

		$this->assertSame( [
			'generated_at' => $expected[ 'generated_at' ],
			'summary'      => $expected[ 'summary' ],
			'items'        => $expected[ 'items' ],
		], $this->extractPayload( $data ) );
		$this->assertArrayNotHasKey( 'groups', $data );
	}

	public function test_posture_routes_report_all_clear_attention_when_no_items_need_action() :void {
		$canonicalAttention = self::con()->comps->site_query->attention();
		$this->assertSame( 0, $canonicalAttention[ 'summary' ][ 'total' ] );
		$this->assertSame( 'good', $canonicalAttention[ 'summary' ][ 'severity' ] );
		$this->assertTrue( $canonicalAttention[ 'summary' ][ 'is_all_clear' ] );
		$this->assertSame( [], $canonicalAttention[ 'items' ] );

		$attentionResponse = $this->dispatchReadRoute( '/shield/v1/posture/attention' );
		$attentionData = $this->assertSuccessfulResponse( $attentionResponse );
		$attentionPayload = $this->extractPayload( $attentionData );

		$this->assertSame( 0, $attentionPayload[ 'summary' ][ 'total' ] );
		$this->assertSame( 'good', $attentionPayload[ 'summary' ][ 'severity' ] );
		$this->assertTrue( $attentionPayload[ 'summary' ][ 'is_all_clear' ] );
		$this->assertSame( [], $attentionPayload[ 'items' ] );

		$overviewResponse = $this->dispatchReadRoute( '/shield/v1/posture/overview' );
		$overviewData = $this->assertSuccessfulResponse( $overviewResponse );
		$overviewPayload = $this->extractPayload( $overviewData );

		$this->assertSame( [
			'total'        => 0,
			'severity'     => 'good',
			'is_all_clear' => true,
		], $overviewPayload[ 'attention_summary' ] );
	}

	public function test_activity_recent_route_returns_canonical_recent_activity_payload() :void {
		$recentEvents = \array_filter(
			self::con()->comps->events->getEvents(),
			static fn( array $event ) :bool => !empty( $event[ 'recent' ] )
		);

		if ( \count( $recentEvents ) < 2 ) {
			$this->markTestSkipped( 'At least two recent events are required for recent activity route coverage.' );
		}

		$recordedKey = \array_key_first( $recentEvents );
		$this->assertIsString( $recordedKey );
		$this->assertTrue( self::con()->db_con->events->commitEvent( $recordedKey ) );

		$response = $this->dispatchReadRoute( '/shield/v1/activity/recent' );
		$data = $this->assertSuccessfulResponse( $response );
		$expected = self::con()->comps->site_query->recentActivity();
		$expected[ 'generated_at' ] = $data[ 'generated_at' ];

		$this->assertSame( $expected, $this->extractPayload( $data ) );
	}

	public function test_scan_results_route_returns_canonical_scan_findings_results_subset() :void {
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

		$request = new \WP_REST_Request( 'GET', '/shield/v1/scan_results' );
		$request->set_param( 'scan_slugs', [ 'wpv', 'apc' ] );
		$request->set_param( 'filter_item_state', 'is_vulnerable' );

		$response = $this->resetRestServer()->dispatch( $request );
		$data = $this->assertSuccessfulResponse( $response );
		$expected = self::con()->comps->site_query->scanFindings( [ 'wpv', 'apc' ], [ 'is_vulnerable' ] );

		$this->assertSame( $expected[ 'results' ], $this->extractPayload( $data ) );
	}

	public function test_routes_reject_unauthenticated_requests() :void {
		\wp_set_current_user( 0 );
		$this->setSecurityAdminContext( false );

		foreach ( [
			'/shield/v1/posture/overview',
			'/shield/v1/posture/attention',
			'/shield/v1/activity/recent',
			'/shield/v1/scan_results',
		] as $routePath ) {
			$response = $this->dispatchReadRoute( $routePath );
			$this->assertContains( $response->get_status(), [ 401, 403 ], $routePath );
			$this->assertNotSame( 0, (int)( $response->get_data()[ 'error_code' ] ?? 1 ), $routePath );
		}
	}

	private function assertSuccessfulResponse( \WP_REST_Response $response ) :array {
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 0, $data[ 'error_code' ] );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertIsArray( $data[ 'meta' ] );
		$this->assertArrayHasKey( 'ts', $data[ 'meta' ] );
		$this->assertArrayHasKey( 'api_version', $data[ 'meta' ] );
		$this->assertArrayHasKey( 'from_cache', $data[ 'meta' ] );

		return $data;
	}

	private function dispatchReadRoute( string $routePath ) :\WP_REST_Response {
		$request = new \WP_REST_Request( 'GET', $routePath );
		return $this->resetRestServer()->dispatch( $request );
	}

	private function extractPayload( array $data ) :array {
		unset( $data[ 'error_code' ], $data[ 'meta' ] );
		return $data;
	}

	private function resetRestServer() :\WP_REST_Server {
		global $wp_rest_server;
		$wp_rest_server = null;
		return \rest_get_server();
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
