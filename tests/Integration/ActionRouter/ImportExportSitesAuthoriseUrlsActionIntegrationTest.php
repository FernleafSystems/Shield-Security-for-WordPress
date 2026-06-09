<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\ImportExportSitesAuthoriseUrlsSubmit,
	Actions\PluginImportExport_NetworkInviteRequest,
	Exceptions\SecurityAdminRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\{
	QueueScheduler,
	SiteRepository
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\VOs\WpHttpResponseVo;
use FernleafSystems\Wordpress\Services\Utilities\HttpRequest;

class ImportExportSitesAuthoriseUrlsActionIntegrationTest extends ShieldIntegrationTestCase {

	private const AUTHORISE_ONE = 'https://93.184.216.61/path';
	private const AUTHORISE_TWO = 'https://93.184.216.62';
	private const INVITE_FAILS = 'https://93.184.216.63';
	private const NO_CONFIRM = 'https://93.184.216.64';
	private const NOT_SECURITY_ADMIN = 'https://93.184.216.65';
	private const VALID_MIXED_WITH_INVALID = 'https://93.184.216.66';
	private const DISABLED_SYNC = 'https://93.184.216.67';
	private const UNAVAILABLE_SYNC = 'https://93.184.216.68';
	private const EXISTING = 'https://93.184.216.69';
	private const REACTIVATED = 'https://93.184.216.70';
	private const SAME_HOST_HOME = 'https://93.184.216.60/import3';
	private const SAME_HOST_CLIENT = 'https://93.184.216.60/import4';
	private const ROOT_SAME_HOST_HOME = 'https://93.184.216.73';
	private const ROOT_SAME_HOST_CLIENT = 'https://93.184.216.73/import4';

	private array $optionsSnapshot = [];
	private array $servicesSnapshot = [];
	private ImportExportSitesInviteHttpRequestRecorder $inviteHttp;

	public function set_up() {
		parent::set_up();
		$this->requireDb( SitesDB::DB_KEY );
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->inviteHttp = new ImportExportSitesInviteHttpRequestRecorder();
		ServicesState::mergeItems( [
			'service_httprequest' => $this->inviteHttp,
		] );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
			'importexport_pending_network_invites',
			'importexport_network_invite_block_until',
			'importexport_sites_migrated_at',
		] );
		$this->requireController()->opts
								  ->optSet( 'importexport_enable', 'N' )
								  ->optSet( NetworkInviteRepository::OPTION_KEY, [] )
								  ->optSet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY, 0 )
								  ->optSet( 'importexport_sites_migrated_at', 0 )
								  ->store();
		$this->loginAsSecurityAdmin();
		$this->clearQueueSchedule();
	}

	public function tear_down() {
		$this->clearQueueSchedule();
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		ServicesState::restore( $this->servicesSnapshot );
		parent::tear_down();
	}

	public function test_authorise_urls_submit_adds_multiple_canonical_urls_schedules_queue_and_sends_invites() :void {
		$this->enableSync();
		$this->seedPendingInvite( 'https://93.184.216.90/pending-master' );
		$this->captureShieldEvents();

		$payload = $this->submitAuthoriseUrls(
			self::AUTHORISE_ONE."/?utm=1\n\n".self::AUTHORISE_TWO."/\n".self::AUTHORISE_ONE."/"
		);

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertArrayHasKey( 'authorised_count', $payload );
		$this->assertArrayHasKey( 'already_authorised_count', $payload );
		$this->assertArrayHasKey( 'total_count', $payload );
		$this->assertArrayNotHasKey( 'invite_attempted_count', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertTrue( $payload[ 'page_reload' ] );
		$this->assertSame( 2, $payload[ 'authorised_count' ] );
		$this->assertSame( 0, $payload[ 'already_authorised_count' ] );
		$this->assertSame( 2, $payload[ 'total_count' ] );

		$one = $this->requireSite( self::AUTHORISE_ONE );
		$two = $this->requireSite( self::AUTHORISE_TWO );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $one->status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $one->queue_status );
		$this->assertSame( SitesDB::SOURCE_MANUAL, $one->source );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $two->status );
		$this->assertNotFalse( \wp_next_scheduled( $this->queueHook() ) );
		$this->assertCount( 2, $this->inviteHttp->requests );
		$this->assertTrue( $this->inviteHttp->requests[ 0 ][ 'reject_unsafe_urls' ] );
		$this->assertTrue( $this->inviteHttp->requests[ 1 ][ 'reject_unsafe_urls' ] );
		$this->assertStringContainsString( PluginImportExport_NetworkInviteRequest::SLUG, $this->inviteHttp->requests[ 0 ][ 'url' ] );
		$this->assertStringContainsString( PluginImportExport_NetworkInviteRequest::SLUG, $this->inviteHttp->requests[ 1 ][ 'url' ] );
		$this->assertCount( 2, $this->getCapturedEventsByKey( 'whitelist_site_added' ) );
		$this->assertSame( [], $this->requireController()->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY ) );
	}

	public function test_authorise_urls_submit_allows_same_host_sibling_path_for_subdirectory_home() :void {
		$this->enableSync();
		$homeFilter = static fn() :string => self::SAME_HOST_HOME;
		\add_filter( 'home_url', $homeFilter );

		try {
			$payload = $this->submitAuthoriseUrls( self::SAME_HOST_CLIENT.'/?utm=1' );
		}
		finally {
			\remove_filter( 'home_url', $homeFilter );
		}

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( 1, $payload[ 'authorised_count' ] );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $this->requireSite( self::SAME_HOST_CLIENT )->status );
		$this->assertCount( 1, $this->inviteHttp->requests );
		$this->assertTrue( $this->inviteHttp->requests[ 0 ][ 'reject_unsafe_urls' ] );
		$this->assertSame( self::SAME_HOST_HOME, $this->inviteHttp->requests[ 0 ][ 'body' ][ 'master_url' ] );
	}

	public function test_authorise_urls_submit_allows_same_host_child_path_for_root_home() :void {
		$this->enableSync();
		$homeFilter = static fn() :string => self::ROOT_SAME_HOST_HOME;
		\add_filter( 'home_url', $homeFilter );

		try {
			$payload = $this->submitAuthoriseUrls( self::ROOT_SAME_HOST_CLIENT.'/?utm=1' );
		}
		finally {
			\remove_filter( 'home_url', $homeFilter );
		}

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( 1, $payload[ 'authorised_count' ] );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $this->requireSite( self::ROOT_SAME_HOST_CLIENT )->status );
		$this->assertCount( 1, $this->inviteHttp->requests );
		$this->assertTrue( $this->inviteHttp->requests[ 0 ][ 'reject_unsafe_urls' ] );
		$this->assertSame( '/import4', \wp_parse_url( $this->inviteHttp->requests[ 0 ][ 'url' ], \PHP_URL_PATH ) );
		$this->assertSame( self::ROOT_SAME_HOST_HOME, $this->inviteHttp->requests[ 0 ][ 'body' ][ 'master_url' ] );
	}

	public function test_authorise_urls_submit_invite_failure_does_not_roll_back_site_row() :void {
		$this->enableSync();
		$this->inviteHttp->failRequests();

		$payload = $this->submitAuthoriseUrls( self::INVITE_FAILS );

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( 1, $payload[ 'authorised_count' ] );
		$this->assertCount( 1, $this->inviteHttp->requests );
		$this->assertTrue( $this->inviteHttp->requests[ 0 ][ 'reject_unsafe_urls' ] );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $this->requireSite( self::INVITE_FAILS )->status );
	}

	public function test_authorise_urls_submit_requires_confirmation_without_mutation() :void {
		$this->enableSync();

		$payload = $this->submitAuthoriseUrls( self::NO_CONFIRM, false );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( self::NO_CONFIRM );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
		$this->assertCount( 0, $this->inviteHttp->requests );
	}

	public function test_authorise_urls_submit_requires_security_admin_without_mutation() :void {
		$this->enableSync();
		$this->setSecurityAdminContext( false );

		try {
			$this->expectException( SecurityAdminRequiredException::class );
			$this->submitAuthoriseUrls( self::NOT_SECURITY_ADMIN );
		}
		finally {
			$this->assertNoSite( self::NOT_SECURITY_ADMIN );
			$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
		}
	}

	public function test_authorise_urls_submit_rejects_mixed_invalid_input_without_mutation() :void {
		$this->enableSync();

		$payload = $this->submitAuthoriseUrls( self::VALID_MIXED_WITH_INVALID."\nnot-a-url" );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( self::VALID_MIXED_WITH_INVALID );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
		$this->assertCount( 0, $this->inviteHttp->requests );
	}

	public function test_authorise_urls_submit_rejects_private_url_without_mutation() :void {
		$this->enableSync();

		$payload = $this->submitAuthoriseUrls( 'https://10.0.0.25/private-client' );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( 'https://10.0.0.25/private-client' );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
		$this->assertCount( 0, $this->inviteHttp->requests );
	}

	public function test_authorise_urls_submit_rejects_disabled_sync_without_mutation() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$payload = $this->submitAuthoriseUrls( self::DISABLED_SYNC );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( self::DISABLED_SYNC );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
		$this->assertCount( 0, $this->inviteHttp->requests );
	}

	public function test_authorise_urls_submit_rejects_unavailable_sync_without_mutation() :void {
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();

		$payload = $this->submitAuthoriseUrls( self::UNAVAILABLE_SYNC );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( self::UNAVAILABLE_SYNC );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
		$this->assertCount( 0, $this->inviteHttp->requests );
	}

	public function test_authorise_urls_submit_does_not_reload_page_when_active_client_already_exists() :void {
		$this->enableSync();
		$this->repo()->upsertActive( 'https://93.184.216.71', SitesDB::SOURCE_MANUAL, '', true );

		$payload = $this->submitAuthoriseUrls( 'https://93.184.216.72' );

		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
		$this->assertSame( 1, $payload[ 'authorised_count' ] );
	}

	public function test_existing_active_url_is_not_requeued_or_scheduled_by_duplicate_submit() :void {
		$this->enableSync();
		$repo = $this->repo();
		$row = $repo->upsertActive( self::EXISTING, SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $row->url, SitesDB::EXPORT_RESULT_SUCCESS );
		$this->clearQueueSchedule();
		$this->inviteHttp->clearRequests();

		$payload = $this->submitAuthoriseUrls( self::EXISTING.'/' );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertArrayHasKey( 'authorised_count', $payload );
		$this->assertArrayHasKey( 'already_authorised_count', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
		$this->assertSame( 0, $payload[ 'authorised_count' ] );
		$this->assertSame( 1, $payload[ 'already_authorised_count' ] );
		$row = $repo->findByUrl( self::EXISTING, true );
		$this->assertInstanceOf( Record::class, $row );
		$this->assertSame( SitesDB::QUEUE_IDLE, $row->queue_status );
		$this->assertCount( 0, $this->inviteHttp->requests );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
	}

	public function test_deleted_url_reactivation_sends_one_invite_attempt() :void {
		$this->enableSync();
		$repo = $this->repo();
		$repo->upsertActive( self::REACTIVATED, SitesDB::SOURCE_MANUAL, '', true );
		$repo->softDeleteUrl( self::REACTIVATED );
		$this->clearQueueSchedule();
		$this->inviteHttp->clearRequests();

		$payload = $this->submitAuthoriseUrls( self::REACTIVATED );

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( 1, $payload[ 'authorised_count' ] );
		$row = $this->requireSite( self::REACTIVATED, true );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
		$this->assertCount( 1, $this->inviteHttp->requests );
	}

	private function submitAuthoriseUrls( string $urls, bool $confirmed = true ) :array {
		$formData = [
			'urls' => $urls,
		];
		if ( $confirmed ) {
			$formData[ 'confirm' ] = 'Y';
		}

		return ( new ActionProcessor() )->processAction( ImportExportSitesAuthoriseUrlsSubmit::SLUG, [
			'form_data' => $formData,
		] )->payload();
	}

	private function enableSync() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
	}

	private function requireSite( string $url, bool $includeDeleted = false ) :Record {
		$row = $this->repo()->findByUrl( $url, $includeDeleted );
		$this->assertInstanceOf( Record::class, $row );
		return $row;
	}

	private function assertNoSite( string $url ) :void {
		$this->assertNull( $this->repo()->findByUrl( $url, true ) );
	}

	private function repo() :SiteRepository {
		return new SiteRepository();
	}

	private function clearQueueSchedule() :void {
		\wp_clear_scheduled_hook( $this->queueHook() );
	}

	private function queueHook() :string {
		return ( new QueueScheduler() )->hook();
	}

	private function seedPendingInvite( string $masterUrl ) :void {
		$id = \hash( 'sha256', $masterUrl );
		$this->requireController()->opts->optSet( NetworkInviteRepository::OPTION_KEY, [
			$id => [
				'id'         => $id,
				'master_url' => $masterUrl,
				'created_at' => 1712620800,
				'updated_at' => 1712620800,
			],
		] )->store();
	}
}

class ImportExportSitesInviteHttpRequestRecorder extends HttpRequest {

	public array $requests = [];
	private bool $failRequests = false;

	public function failRequests() :void {
		$this->failRequests = true;
	}

	public function clearRequests() :void {
		$this->requests = [];
	}

	public function post( string $url, $args = [] ) :bool {
		$this->requests[] = [
			'url'                => $url,
			'body'               => \is_array( $args[ 'body' ] ?? null ) ? $args[ 'body' ] : [],
			'reject_unsafe_urls' => (bool)( $args[ 'reject_unsafe_urls' ] ?? false ),
		];

		if ( $this->failRequests ) {
			$this->lastError = new \WP_Error( 'invite_failed', 'Invite failed' );
			$this->lastResponse = null;
			return false;
		}

		$this->lastResponse = ( new WpHttpResponseVo() )->applyFromArray( [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
		] );
		return true;
	}
}
