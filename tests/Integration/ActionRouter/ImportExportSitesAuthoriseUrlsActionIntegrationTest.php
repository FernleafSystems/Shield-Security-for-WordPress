<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\ImportExportSitesAuthoriseUrlsSubmit,
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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ImportExportSitesAuthoriseUrlsActionIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( SitesDB::DB_KEY );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
			'importexport_whitelist',
			'import_url_ids',
			'importexport_sites_migrated_at',
		] );
		$this->requireController()->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_whitelist', [] )
			->optSet( 'import_url_ids', [] )
			->optSet( 'importexport_sites_migrated_at', 0 )
			->store();
		$this->loginAsSecurityAdmin();
		$this->clearQueueSchedule();
	}

	public function tear_down() {
		$this->clearQueueSchedule();
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_authorise_urls_submit_adds_multiple_canonical_urls_and_schedules_queue() :void {
		$this->enableSync();
		$this->captureShieldEvents();

		$payload = $this->submitAuthoriseUrls(
			"https://authorise-one.example.com/path/?utm=1\n\nhttps://authorise-two.example.com/\nhttps://authorise-one.example.com/path/"
		);

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertArrayHasKey( 'authorised_count', $payload );
		$this->assertArrayHasKey( 'already_authorised_count', $payload );
		$this->assertArrayHasKey( 'total_count', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
		$this->assertSame( 2, $payload[ 'authorised_count' ] );
		$this->assertSame( 0, $payload[ 'already_authorised_count' ] );
		$this->assertSame( 2, $payload[ 'total_count' ] );

		$one = $this->requireSite( 'https://authorise-one.example.com/path' );
		$two = $this->requireSite( 'https://authorise-two.example.com' );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $one->status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $one->queue_status );
		$this->assertSame( SitesDB::SOURCE_MANUAL, $one->source );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $two->status );
		$this->assertSame(
			[ 'https://authorise-one.example.com/path', 'https://authorise-two.example.com' ],
			$this->requireController()->opts->optGet( 'importexport_whitelist' )
		);
		$this->assertNotFalse( \wp_next_scheduled( $this->queueHook() ) );
		$this->assertCount( 2, $this->getCapturedEventsByKey( 'whitelist_site_added' ) );
	}

	public function test_authorise_urls_submit_requires_confirmation_without_mutation() :void {
		$this->enableSync();

		$payload = $this->submitAuthoriseUrls( 'https://authorise-no-confirm.example.com', false );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( 'https://authorise-no-confirm.example.com' );
		$this->assertSame( [], $this->requireController()->opts->optGet( 'importexport_whitelist' ) );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
	}

	public function test_authorise_urls_submit_requires_security_admin_without_mutation() :void {
		$this->enableSync();
		$this->setSecurityAdminContext( false );

		try {
			$this->expectException( SecurityAdminRequiredException::class );
			$this->submitAuthoriseUrls( 'https://authorise-not-security-admin.example.com' );
		}
		finally {
			$this->assertNoSite( 'https://authorise-not-security-admin.example.com' );
			$this->assertSame( [], $this->requireController()->opts->optGet( 'importexport_whitelist' ) );
			$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
		}
	}

	public function test_authorise_urls_submit_rejects_mixed_invalid_input_without_mutation() :void {
		$this->enableSync();

		$payload = $this->submitAuthoriseUrls( "https://authorise-valid.example.com\nnot-a-url" );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( 'https://authorise-valid.example.com' );
		$this->assertSame( [], $this->requireController()->opts->optGet( 'importexport_whitelist' ) );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
	}

	public function test_authorise_urls_submit_rejects_disabled_sync_without_mutation() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$payload = $this->submitAuthoriseUrls( 'https://authorise-disabled.example.com' );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( 'https://authorise-disabled.example.com' );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
	}

	public function test_authorise_urls_submit_rejects_unavailable_sync_without_mutation() :void {
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();

		$payload = $this->submitAuthoriseUrls( 'https://authorise-unavailable.example.com' );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertNoSite( 'https://authorise-unavailable.example.com' );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
	}

	public function test_existing_active_url_is_not_requeued_or_scheduled_by_duplicate_submit() :void {
		$this->enableSync();
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://authorise-existing.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $row->url, SitesDB::EXPORT_RESULT_SUCCESS );
		$this->clearQueueSchedule();

		$payload = $this->submitAuthoriseUrls( 'https://authorise-existing.example.com/' );

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'authorised_count', $payload );
		$this->assertArrayHasKey( 'already_authorised_count', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( 0, $payload[ 'authorised_count' ] );
		$this->assertSame( 1, $payload[ 'already_authorised_count' ] );
		$row = $repo->findByUrl( 'https://authorise-existing.example.com', true );
		$this->assertInstanceOf( Record::class, $row );
		$this->assertSame( SitesDB::QUEUE_IDLE, $row->queue_status );
		$this->assertFalse( \wp_next_scheduled( $this->queueHook() ) );
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
}
