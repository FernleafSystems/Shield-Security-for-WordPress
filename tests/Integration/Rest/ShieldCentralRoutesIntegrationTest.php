<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionExecutor,
	ActionNonce,
	Actions\ShieldCentralCreatePairingToken
};
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\{
	ConnectionStore,
	PairingTokenStore
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ShieldCentralRoutesIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'file_locker' );
		\delete_option( PairingTokenStore::OPTION_KEY );
		\delete_option( ConnectionStore::OPTION_KEY );
		\wp_set_current_user( 0 );
	}

	public function tear_down() {
		\delete_option( PairingTokenStore::OPTION_KEY );
		\delete_option( ConnectionStore::OPTION_KEY );
		parent::tear_down();
	}

	public function test_pairing_sync_and_unpair_routes_use_app_level_contract_without_wp_login() :void {
		$this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [ 'rest_api_level_1' ] );
		$issued = $this->issuePairingTokenViaAction( ActionNonce::Create( ShieldCentralCreatePairingToken::class ) );
		\wp_set_current_user( 0 );
		$this->setSecurityAdminContext( false );

		$bootstrap = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/pair/bootstrap',
			[
				'pairing_token' => $issued[ 'pairing_token' ],
				'site_url'      => 'https://submitted.example',
			]
		) );

		$this->assertTrue( $bootstrap[ 'success' ] );
		$this->assertNotEmpty( $bootstrap[ 'pairing_session' ] );

		$health = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/pair/health',
			[
				'pairing_session' => $bootstrap[ 'pairing_session' ],
			]
		) );

		$this->assertTrue( $health[ 'success' ] );
		$this->assertIsArray( $health[ 'capability_report' ] );
		$this->assertContains( 'sync/collect', $health[ 'reported_actions' ] );

		$siteUuid = \wp_generate_uuid4();
		$finalize = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/pair/finalize',
			[
				'pairing_session' => $bootstrap[ 'pairing_session' ],
				'site_uuid'       => $siteUuid,
			]
		) );

		$this->assertTrue( $finalize[ 'success' ] );
		$this->assertSame( 'hmac', $finalize[ 'preferred_auth_mode' ] );
		$this->assertStringContainsString( 'shield/v1/shield-central', $finalize[ 'trusted_endpoint_url' ] );
		$this->assertNotEmpty( $finalize[ 'connection_material' ][ 'shared_secret' ] );

		$sync = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/sync/collect',
			[
				'site_uuid'           => $siteUuid,
				'auth_mode'           => 'hmac',
				'connection_material' => $finalize[ 'connection_material' ],
			]
		) );

		$this->assertTrue( $sync[ 'success' ] );
		$this->assertIsArray( $sync[ 'wordpress_info' ] );
		$this->assertIsArray( $sync[ 'plugins' ] );
		$this->assertIsArray( $sync[ 'themes' ] );
		$this->assertIsArray( $sync[ 'security_issues' ] );

		$unpair = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/site/unpair',
			[
				'site_uuid'           => $siteUuid,
				'connection_material' => $finalize[ 'connection_material' ],
			]
		) );

		$this->assertTrue( $unpair[ 'success' ] );

		$afterUnpair = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/sync/collect',
			[
				'site_uuid'           => $siteUuid,
				'connection_material' => $finalize[ 'connection_material' ],
			]
		) );

		$this->assertFalse( $afterUnpair[ 'success' ] );
		$this->assertTrue( $afterUnpair[ 'reconnect_required' ] );
	}

	public function test_pairing_token_action_requires_authenticated_security_admin() :void {
		$this->enablePremiumCapabilities( [ 'rest_api_level_1' ] );
		$userId = $this->loginAsSecurityAdmin();
		$nonce = ActionNonce::Create( ShieldCentralCreatePairingToken::class );
		\wp_set_current_user( 0 );
		$this->setSecurityAdminContext( false );

		$response = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/action/'.ShieldCentralCreatePairingToken::SLUG,
			[
				ActionData::FIELD_NONCE => $nonce,
				'payload'               => [],
			]
		) );

		$this->assertFalse( $response[ 'success' ] );
		$this->assertIsArray( $response[ 'data' ] );
		$this->assertArrayHasKey( 'success', $response[ 'data' ] );
		$this->assertFalse( (bool)$response[ 'data' ][ 'success' ] );
		$this->assertArrayNotHasKey( 'pairing_token', $response[ 'data' ] );
		\wp_set_current_user( $userId );
	}

	public function test_pairing_token_action_rejects_invalid_nonce() :void {
		$this->enablePremiumCapabilities( [ 'rest_api_level_1' ] );
		$this->loginAsSecurityAdmin();

		$filter = static function () {
			return static function ( $message, $title = '', $args = [] ) :void {
				unset( $message, $title );
				throw new ShieldCentralActionWpDieException( \is_array( $args ) ? $args : [] );
			};
		};
		\add_filter( 'wp_die_handler', $filter );

		try {
			$this->dispatchPostRoute(
				'/shield/v1/action/'.ShieldCentralCreatePairingToken::SLUG,
				[
					ActionData::FIELD_NONCE => 'invalid',
					'payload'               => [],
				]
			);
			$this->fail( 'Expected invalid ShieldCentral token action nonce to fail.' );
		}
		catch ( ShieldCentralActionWpDieException $e ) {
			$this->assertSame( ActionExecutor::WP_DIE_INVALID_NONCE_CODE, $e->args()[ 'code' ] ?? '' );
			$this->assertSame( ActionExecutor::WP_DIE_INVALID_NONCE_STATUS, $e->args()[ 'response' ] ?? 0 );
		}
		finally {
			\remove_filter( 'wp_die_handler', $filter );
		}
	}

	public function test_invalid_token_and_invalid_connection_are_app_level_failures() :void {
		$bootstrap = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/pair/bootstrap',
			[
				'pairing_token' => 'invalid',
			]
		) );

		$this->assertFalse( $bootstrap[ 'success' ] );
		$this->assertSame( 'invalid_pairing_token', $bootstrap[ 'diagnostic_code' ] );

		$sync = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/sync/collect',
			[
				'site_uuid'           => 'site-uuid',
				'connection_material' => [ 'shared_secret' => 'wrong' ],
			]
		) );

		$this->assertFalse( $sync[ 'success' ] );
		$this->assertTrue( $sync[ 'reconnect_required' ] );
		$this->assertSame( 'invalid_connection_material', $sync[ 'diagnostic_code' ] );
	}

	public function test_pair_cleanup_removes_connection_only_with_valid_material() :void {
		$siteUuid = \wp_generate_uuid4();
		$connection = ( new ConnectionStore() )->create( $siteUuid );

		$invalidCleanup = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/pair/cleanup',
			[
				'site_uuid'           => $siteUuid,
				'connection_material' => [ 'shared_secret' => 'wrong' ],
			]
		) );

		$this->assertFalse( $invalidCleanup[ 'success' ] );
		$this->assertTrue( $invalidCleanup[ 'reconnect_required' ] );
		$this->assertSame( 'invalid_connection_material', $invalidCleanup[ 'diagnostic_code' ] );

		$syncAfterInvalidCleanup = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/sync/collect',
			[
				'site_uuid'           => $siteUuid,
				'connection_material' => $connection[ 'connection_material' ],
			]
		) );

		$this->assertTrue( $syncAfterInvalidCleanup[ 'success' ] );

		$cleanup = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/pair/cleanup',
			[
				'site_uuid'           => $siteUuid,
				'connection_material' => $connection[ 'connection_material' ],
			]
		) );

		$this->assertTrue( $cleanup[ 'success' ] );

		$syncAfterCleanup = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/sync/collect',
			[
				'site_uuid'           => $siteUuid,
				'connection_material' => $connection[ 'connection_material' ],
			]
		) );

		$this->assertFalse( $syncAfterCleanup[ 'success' ] );
		$this->assertTrue( $syncAfterCleanup[ 'reconnect_required' ] );
	}

	public function test_sync_collect_emits_security_issues_from_scan_results() :void {
		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => self::con()->base_file,
			'is_vulnerable' => 1,
		] );

		$siteUuid = \wp_generate_uuid4();
		$connection = ( new ConnectionStore() )->create( $siteUuid );

		$sync = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/sync/collect',
			[
				'site_uuid'           => $siteUuid,
				'connection_material' => $connection[ 'connection_material' ],
			]
		) );

		$this->assertTrue( $sync[ 'success' ] );
		$this->assertContainsCanonicalIssue(
			$sync[ 'security_issues' ],
			'scans',
			'vulnerable_assets',
			'critical',
			'plugin',
			self::con()->base_file
		);
	}

	public function test_sync_collect_excludes_ignored_and_auto_filtered_scan_results() :void {
		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		$ignoredAssetKey = 'ignored-plugin/ignored.php';
		$autoFilteredAssetKey = 'auto-filtered-plugin/auto.php';

		$ignored = TestDataFactory::insertScanResultItemTracked( $wpvId, [
			'item_id'       => $ignoredAssetKey,
			'is_vulnerable' => 1,
		] );
		TestDataFactory::markScanResultItemIgnored( $ignored[ 'result_item_id' ] );

		$autoFiltered = TestDataFactory::insertScanResultItemTracked( $wpvId, [
			'item_id'       => $autoFilteredAssetKey,
			'is_vulnerable' => 1,
		] );
		TestDataFactory::markScanResultItemAutoFiltered( $autoFiltered[ 'result_item_id' ] );

		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => self::con()->base_file,
			'is_vulnerable' => 1,
		] );

		$siteUuid = \wp_generate_uuid4();
		$connection = ( new ConnectionStore() )->create( $siteUuid );

		$sync = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/shield-central/sync/collect',
			[
				'site_uuid'           => $siteUuid,
				'connection_material' => $connection[ 'connection_material' ],
			]
		) );

		$this->assertTrue( $sync[ 'success' ] );
		$this->assertContainsCanonicalIssue(
			$sync[ 'security_issues' ],
			'scans',
			'vulnerable_assets',
			'critical',
			'plugin',
			self::con()->base_file
		);
		$this->assertDoesNotContainIssueForAssetKey( $sync[ 'security_issues' ], $ignoredAssetKey );
		$this->assertDoesNotContainIssueForAssetKey( $sync[ 'security_issues' ], $autoFilteredAssetKey );
	}

	private function dispatchPostRoute( string $routePath, array $params ) :\WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', $routePath );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->resetRestServer()->dispatch( $request );
	}

	private function assertSuccessfulResponse( \WP_REST_Response $response ) :array {
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 0, $data[ 'error_code' ] );
		$this->assertArrayHasKey( 'meta', $data );

		return $data;
	}

	private function resetRestServer() :\WP_REST_Server {
		global $wp_rest_server;
		$wp_rest_server = null;
		return \rest_get_server();
	}

	private function issuePairingTokenViaAction( string $nonce ) :array {
		$action = $this->assertSuccessfulResponse( $this->dispatchPostRoute(
			'/shield/v1/action/'.ShieldCentralCreatePairingToken::SLUG,
			[
				ActionData::FIELD_NONCE => $nonce,
				'payload'               => [],
			]
		) );

		$this->assertTrue( $action[ 'success' ] );
		$this->assertIsArray( $action[ 'data' ] );
		$this->assertArrayHasKey( 'success', $action[ 'data' ] );
		$this->assertArrayHasKey( 'ttl_seconds', $action[ 'data' ] );
		$this->assertArrayHasKey( 'expires_at', $action[ 'data' ] );
		$this->assertArrayHasKey( 'pairing_token', $action[ 'data' ] );
		$this->assertTrue( (bool)$action[ 'data' ][ 'success' ] );
		$this->assertSame( 900, $action[ 'data' ][ 'ttl_seconds' ] );
		$this->assertNotEmpty( $action[ 'data' ][ 'expires_at' ] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $action[ 'data' ][ 'pairing_token' ] );

		return $action[ 'data' ];
	}

	private function assertContainsCanonicalIssue(
		array $issues,
		string $category,
		string $issueKey,
		string $severity,
		string $assetType,
		string $assetKey
	) :void {
		foreach ( $issues as $issue ) {
			if ( ( $issue[ 'category' ] ?? '' ) === $category
				 && ( $issue[ 'issue_key' ] ?? '' ) === $issueKey
				 && ( $issue[ 'severity' ] ?? '' ) === $severity
				 && ( $issue[ 'asset_type' ] ?? '' ) === $assetType
				 && ( $issue[ 'asset_key' ] ?? '' ) === $assetKey ) {
				return;
			}
		}

		$this->fail( 'Expected canonical ShieldCentral security issue was not emitted.' );
	}

	private function assertDoesNotContainIssueForAssetKey( array $issues, string $assetKey ) :void {
		foreach ( $issues as $issue ) {
			if ( ( $issue[ 'asset_key' ] ?? '' ) === $assetKey ) {
				$this->fail( 'Unexpected ShieldCentral security issue was emitted.' );
			}
		}
	}
}

class ShieldCentralActionWpDieException extends \RuntimeException {

	private array $args;

	public function __construct( array $args ) {
		parent::__construct();
		$this->args = $args;
	}

	public function args() :array {
		return $this->args;
	}
}
