<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	ActionRoutingController,
	CaptureAjaxAction,
	CapturePluginAction,
	Actions\ImportExportNetworkInviteAccept,
	Actions\ImportExportNetworkInviteReject,
	Actions\PluginImportExport_NetworkInviteRequest,
	Exceptions\SecurityAdminRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as SitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\ImportExportNetworkInvite;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportNetworkInviteIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const MASTER_INVITE_URL = 'https://93.184.216.31/master-invite';
	private const UNAVAILABLE_MASTER_URL = 'https://93.184.216.32/unavailable-master';
	private const NOTICE_MASTER_URL = 'https://93.184.216.33/notice-master';
	private const REJECT_MASTER_URL = 'https://93.184.216.34/reject-master';
	private const CONFIRM_MASTER_URL = 'https://93.184.216.35/confirm-master';
	private const DISABLED_ACCEPT_MASTER_URL = 'https://93.184.216.36/disabled-accept-master';
	private const ACCEPT_MASTER_URL = 'https://93.184.216.37/accept-master';
	private const SECADMIN_MASTER_URL = 'https://93.184.216.38/secadmin-master';
	private const AJAX_MASTER_URL = 'https://93.184.216.39/ajax-master';
	private const DIRECT_MASTER_URL = 'https://93.184.216.40/direct-master';
	private const GET_MASTER_URL = 'https://93.184.216.41/get-master';
	private const SECOND_MASTER_URL = 'https://93.184.216.42/second-master';
	private const CONNECTED_MASTER_URL = 'https://93.184.216.43/connected-master';
	private const ACTIVE_CLIENT_MASTER_URL = 'https://93.184.216.44/active-client-master';
	private const LEGACY_CLIENT_MASTER_URL = 'https://93.184.216.45/legacy-client-master';
	private const COOLDOWN_MASTER_URL = 'https://93.184.216.46/cooldown-master';
	private const STALE_MASTER_URL = 'https://93.184.216.47/stale-master';

	private array $optionsSnapshot = [];
	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireDb( SitesDB::DB_KEY );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
			'importexport_masterurl',
			'importexport_whitelist',
			'importexport_pending_network_invites',
			'importexport_network_invite_block_until',
			'importexport_sites_migrated_at',
			'importexport_handshake_expires_at',
			'import_id',
			'xfer_excluded',
		] );
		$this->requireController()->opts
								  ->optSet( 'importexport_enable', 'N' )
								  ->optSet( 'importexport_masterurl', '' )
								  ->optSet( 'importexport_whitelist', [] )
								  ->optSet( NetworkInviteRepository::OPTION_KEY, [] )
								  ->optSet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY, 0 )
								  ->optSet( 'importexport_sites_migrated_at', 1 )
								  ->store();
		\add_filter( 'pre_http_request', [ $this, 'mockMasterExportResponse' ], 10, 3 );
	}

	public function tear_down() {
		\remove_filter( 'pre_http_request', [ $this, 'mockMasterExportResponse' ], 10 );
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_anonymous_invite_request_rejects_disabled_import_export_without_mutation_or_payload() :void {
		$payload = $this->submitAnonymousInvite( self::MASTER_INVITE_URL );

		$this->assertSame( [], $payload );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 'N', (string)$this->requireController()->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( [], $this->requireController()->db_con->import_export_sites->getQuerySelector()->queryWithResult() ?? [] );
	}

	public function test_anonymous_invite_request_rejects_unavailable_import_export_without_mutation_or_payload() :void {
		$this->disablePremiumCapabilities();
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();

		$payload = $this->submitAnonymousInvite( self::UNAVAILABLE_MASTER_URL );

		$this->assertSame( [], $payload );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
		$this->assertFalse( ( new ImportExportController() )->isSyncAvailable() );
		$this->assertFalse( ( new ImportExportController() )->isSyncEnabled() );
	}

	public function test_anonymous_invite_request_stores_only_pending_invite_when_import_export_enabled_without_payload() :void {
		$this->enableSync();

		$payload = $this->submitAnonymousInvite( self::MASTER_INVITE_URL );

		$this->assertSame( [], $payload );
		$this->assertCount( 1, ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 'Y', (string)$this->requireController()->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( [], $this->requireController()->db_con->import_export_sites->getQuerySelector()->queryWithResult() ?? [] );
	}

	public function test_anonymous_invite_rejects_unsafe_url_without_mutation_or_payload() :void {
		$this->enableSync();

		$payload = $this->submitAnonymousInvite( 'http://127.0.0.1' );

		$this->assertSame( [], $payload );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_get_invite_request_does_not_mutate_state() :void {
		$this->enableSync();

		$payload = $this->submitAnonymousInvite( self::GET_MASTER_URL, 'GET' );

		$this->assertSame( [], $payload );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_post_invite_request_ignores_master_url_outside_post_body() :void {
		$this->enableSync();
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
			],
			[],
			[]
		);

		$payload = ( new ActionProcessor() )->processAction( PluginImportExport_NetworkInviteRequest::SLUG, [
			'master_url' => self::MASTER_INVITE_URL,
		] )->payload();

		$this->assertSame( [], $payload );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_pending_invites_are_single_and_ignore_followup_requests() :void {
		$this->enableSync();
		$repo = new NetworkInviteRepository();
		$first = $repo->receive( self::MASTER_INVITE_URL );
		$duplicate = $repo->receive( self::MASTER_INVITE_URL );
		$second = $repo->receive( self::SECOND_MASTER_URL );

		$pending = $repo->pending();
		$this->assertIsArray( $first );
		$this->assertNull( $duplicate );
		$this->assertNull( $second );
		$this->assertCount( 1, $pending );
		$this->assertSame( self::MASTER_INVITE_URL, $pending[ 0 ][ 'master_url' ] );
		$this->assertSame( $first[ 'updated_at' ], $pending[ 0 ][ 'updated_at' ] );
		$this->assertNull( $repo->find( \hash( 'sha256', self::SECOND_MASTER_URL ) ) );
	}

	public function test_anonymous_invite_request_rejects_when_site_already_has_master_url_without_mutation_or_payload() :void {
		$this->enableSync();
		$this->requireController()->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();

		$payload = $this->submitAnonymousInvite( self::CONNECTED_MASTER_URL );

		$this->assertSame( [], $payload );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( [], $this->requireController()->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
	}

	public function test_anonymous_invite_request_rejects_when_site_has_active_client_rows_without_mutation_or_payload() :void {
		$this->enableSync();
		( new SiteRepository() )->upsertActive( 'https://93.184.216.72/client-site', SitesDB::SOURCE_MANUAL, '', true );

		$payload = $this->submitAnonymousInvite( self::ACTIVE_CLIENT_MASTER_URL );

		$this->assertSame( [], $payload );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( [], $this->requireController()->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
	}

	public function test_anonymous_invite_request_rejects_when_legacy_client_rows_import_as_active_without_payload() :void {
		$this->enableSync();
		$this->requireController()->opts
								  ->optSet( 'importexport_sites_migrated_at', 0 )
								  ->optSet( 'importexport_whitelist', [ 'https://93.184.216.73/legacy-client' ] )
								  ->store();

		$payload = $this->submitAnonymousInvite( self::LEGACY_CLIENT_MASTER_URL );

		$this->assertSame( [], $payload );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertGreaterThan( 0, ( new SiteRepository() )->countActiveRows() );
	}

	public function test_notice_is_non_dismissible_and_links_to_review() :void {
		$this->enableSync();
		$invite = ( new NetworkInviteRepository() )->receive( self::NOTICE_MASTER_URL );

		$notice = ( new ImportExportNetworkInvite() )->check();

		$this->assertIsArray( $notice );
		$this->assertFalse( (bool)$notice[ 'can_dismiss' ] );
		$this->assertContains( 'shield_admin_top_page', $notice[ 'locations' ] );
		$this->assertStringContainsString( $invite[ 'id' ], \implode( ' ', $notice[ 'text' ] ) );
	}

	public function test_reject_clears_invite_without_sync_state_change() :void {
		$this->enableSync();
		$invite = ( new NetworkInviteRepository() )->receive( self::REJECT_MASTER_URL );

		$payload = ( new ActionProcessor() )->processAction( ImportExportNetworkInviteReject::SLUG, [
			'form_params' => [
				'invite_id' => $invite[ 'id' ],
			],
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertGreaterThan( Services::Request()->ts(), (int)$this->requireController()->opts->optGet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY ) );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 'Y', (string)$this->requireController()->opts->optGet( 'importexport_enable' ) );
	}

	public function test_rejected_invite_blocks_future_invites_for_one_week() :void {
		$this->enableSync();
		$repo = new NetworkInviteRepository();
		$invite = $repo->receive( self::REJECT_MASTER_URL );
		( new ActionProcessor() )->processAction( ImportExportNetworkInviteReject::SLUG, [
			'form_params' => [
				'invite_id' => $invite[ 'id' ],
			],
		] );

		$blockedPayload = $this->submitAnonymousInvite( self::COOLDOWN_MASTER_URL );
		$this->assertSame( [], $blockedPayload );
		$this->assertSame( [], $repo->pending() );
		$this->assertSame( [], $this->requireController()->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );

		$this->requireController()->opts->optSet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY, Services::Request()->ts() - 1 )->store();
		$expiredPayload = $this->submitAnonymousInvite( self::COOLDOWN_MASTER_URL );

		$this->assertSame( [], $expiredPayload );
		$this->assertCount( 1, $repo->pending() );
		$this->assertSame( self::COOLDOWN_MASTER_URL, $repo->first()[ 'master_url' ] );
	}

	public function test_accept_requires_checkbox_and_keeps_invite_on_failure() :void {
		$this->enableSync();
		$invite = ( new NetworkInviteRepository() )->receive( self::CONFIRM_MASTER_URL );

		$payload = ( new ActionProcessor() )->processAction( ImportExportNetworkInviteAccept::SLUG, [
			'form_params' => [
				'invite_id' => $invite[ 'id' ],
			],
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNotNull( ( new NetworkInviteRepository() )->find( $invite[ 'id' ] ) );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_accept_requires_enabled_import_export_and_keeps_invite_on_failure() :void {
		$this->enableSync();
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();
		$invite = $this->seedPendingInvite( self::DISABLED_ACCEPT_MASTER_URL );

		$payload = ( new ActionProcessor() )->processAction( ImportExportNetworkInviteAccept::SLUG, [
			'form_params' => [
				'invite_id' => $invite[ 'id' ],
				'confirm'   => 'Y',
			],
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertArrayHasKey( $invite[ 'id' ], $this->requireController()->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 'N', (string)$this->requireController()->opts->optGet( 'importexport_enable' ) );
	}

	public function test_accept_imports_from_master_sets_master_and_clears_invite() :void {
		$this->enableSync();
		$master = self::ACCEPT_MASTER_URL;
		$invite = ( new NetworkInviteRepository() )->receive( $master );

		$payload = ( new ActionProcessor() )->processAction( ImportExportNetworkInviteAccept::SLUG, [
			'form_params' => [
				'invite_id' => $invite[ 'id' ],
				'confirm'   => 'Y',
			],
		] )->payload();

		$this->assertTrue( $payload[ 'success' ], (string)\wp_json_encode( $payload ) );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( $master, (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 'Y', (string)$this->requireController()->opts->optGet( 'importexport_enable' ) );
	}

	public function test_accept_rejects_stale_invite_after_site_gets_master_url_without_import() :void {
		$this->enableSync();
		$invite = ( new NetworkInviteRepository() )->receive( self::STALE_MASTER_URL );
		$this->requireController()->opts->optSet( 'importexport_masterurl', 'https://existing-master.example.com' )->store();

		$payload = ( new ActionProcessor() )->processAction( ImportExportNetworkInviteAccept::SLUG, [
			'form_params' => [
				'invite_id' => $invite[ 'id' ],
				'confirm'   => 'Y',
			],
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( 'importexport_handshake_expires_at' ) );
	}

	public function test_accept_rejects_stale_invite_after_site_gets_active_client_row_without_import_or_clear() :void {
		$this->enableSync();
		$invite = ( new NetworkInviteRepository() )->receive( self::STALE_MASTER_URL );
		( new SiteRepository() )->upsertActive( 'https://93.184.216.74/client-site', SitesDB::SOURCE_MANUAL, '', true );

		$payload = ( new ActionProcessor() )->processAction( ImportExportNetworkInviteAccept::SLUG, [
			'form_params' => [
				'invite_id' => $invite[ 'id' ],
				'confirm'   => 'Y',
			],
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertArrayHasKey( $invite[ 'id' ], $this->requireController()->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( 'importexport_handshake_expires_at' ) );
	}

	public function test_accept_rejects_private_invite_without_import_or_clear() :void {
		$this->enableSync();
		$privateMaster = 'https://10.0.0.25/private-master';
		$id = \hash( 'sha256', $privateMaster );
		$this->requireController()->opts->optSet( NetworkInviteRepository::OPTION_KEY, [
			$id => [
				'id'         => $id,
				'master_url' => $privateMaster,
				'created_at' => 1712620800,
				'updated_at' => 1712620800,
			],
		] )->store();

		$payload = ( new ActionProcessor() )->processAction( ImportExportNetworkInviteAccept::SLUG, [
			'form_params' => [
				'invite_id' => $id,
				'confirm'   => 'Y',
			],
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] );
		$this->assertArrayHasKey( $id, $this->requireController()->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
		$this->assertSame( '', (string)$this->requireController()->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( 'importexport_handshake_expires_at' ) );
	}

	public function test_accept_requires_security_admin() :void {
		$this->enableSync();
		$invite = ( new NetworkInviteRepository() )->receive( self::SECADMIN_MASTER_URL );
		$this->setSecurityAdminContext( false );

		$this->expectException( SecurityAdminRequiredException::class );
		( new ActionProcessor() )->processAction( ImportExportNetworkInviteAccept::SLUG, [
			'form_params' => [
				'invite_id' => $invite[ 'id' ],
				'confirm'   => 'Y',
			],
		] );
	}

	public function test_invite_request_is_denied_from_ajax_transport() :void {
		$this->enableSync();
		$actionData = ActionData::Build( PluginImportExport_NetworkInviteRequest::class );
		$this->applyCurrentShieldAjaxRequest( \array_merge( $actionData, [
			'master_url' => self::AJAX_MASTER_URL,
		] ), false );

		$subject = new ImportExportNetworkInviteCaptureAjaxActionTestDouble();

		$this->assertFalse( ( new \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalActionTransportPolicy() )->isAllowed(
			PluginImportExport_NetworkInviteRequest::SLUG,
			$actionData,
			ActionRoutingController::ACTION_AJAX
		) );
		$this->assertFalse( $subject->canRunForTest() );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
	}

	public function test_direct_invite_request_transport_is_silent_for_stored_and_rejected_branches() :void {
		$this->enableSync();
		$storedOutput = $this->captureDirectInviteRequestOutput( self::DIRECT_MASTER_URL );

		$this->assertSame( '', $storedOutput );
		$this->assertCount( 1, ( new NetworkInviteRepository() )->pending() );

		$duplicateOutput = $this->captureDirectInviteRequestOutput( self::SECOND_MASTER_URL );
		$this->assertSame( '', $duplicateOutput );
		$this->assertCount( 1, ( new NetworkInviteRepository() )->pending() );

		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();
		$rejectedOutput = $this->captureDirectInviteRequestOutput( 'http://127.0.0.1' );

		$this->assertSame( '', $rejectedOutput );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );

		$this->enableSync();
		$this->requireController()->opts->optSet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY, Services::Request()->ts() + \WEEK_IN_SECONDS )->store();
		$cooldownOutput = $this->captureDirectInviteRequestOutput( self::COOLDOWN_MASTER_URL );

		$this->assertSame( '', $cooldownOutput );
		$this->assertSame( [], ( new NetworkInviteRepository() )->pending() );
	}

	public function mockMasterExportResponse( $preempt, array $args, string $url ) {
		if ( !\str_contains( $url, 'importexport_export' ) ) {
			return $preempt;
		}

		return [
			'headers'  => [],
			'body'     => \wp_json_encode( [
				'success' => true,
				'data'    => [
					'options'  => [],
					'ip_rules' => [],
				],
			] ),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
		];
	}

	private function submitAnonymousInvite( string $masterUrl, string $method = 'POST' ) :array {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => $method,
			],
			[],
			[
				'master_url' => $masterUrl,
			]
		);

		return ( new ActionProcessor() )->processAction( PluginImportExport_NetworkInviteRequest::SLUG, [
			'master_url' => $masterUrl,
		] )->payload();
	}

	private function seedPendingInvite( string $masterUrl ) :array {
		$id = \hash( 'sha256', $masterUrl );
		$invite = [
			'id'         => $id,
			'master_url' => $masterUrl,
			'created_at' => 1712620800,
			'updated_at' => 1712620800,
		];
		$this->requireController()->opts->optSet( NetworkInviteRepository::OPTION_KEY, [
			$id => $invite,
		] )->store();
		return $invite;
	}

	private function captureDirectInviteRequestOutput( string $masterUrl ) :string {
		$actionData = ActionData::Build( PluginImportExport_NetworkInviteRequest::class );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/?'.\http_build_query( $actionData ),
			],
			$actionData,
			[
				'master_url' => $masterUrl,
			]
		);
		$this->requireController()->this_req->wp_is_ajax = false;

		$level = \ob_get_level();
		\ob_start();
		try {
			( new ImportExportNetworkInviteCapturePluginActionTestDouble() )->execute();
			return \trim( \ob_get_level() > $level ? (string)\ob_get_clean() : '' );
		}
		finally {
			while ( \ob_get_level() > $level ) {
				\ob_end_clean();
			}
		}
	}

	private function enableSync() :void {
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
	}
}

class ImportExportNetworkInviteCapturePluginActionTestDouble extends CapturePluginAction {
}

class ImportExportNetworkInviteCaptureAjaxActionTestDouble extends CaptureAjaxAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}
}
