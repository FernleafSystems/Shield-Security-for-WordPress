<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as ImportExportSitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\QueueScheduler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportController {
	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	public const SYNC_STATE_UNAVAILABLE = 'unavailable';
	public const SYNC_STATE_DISABLED = 'disabled';
	public const SYNC_STATE_ENABLED = 'enabled';

	protected function canRun(): bool {
		$scheduler = $this->queueScheduler();
		return $this->isSyncAvailable()
			   || $scheduler->hasScheduledEvent()
			   || self::con()->opts->optIs( 'importexport_enable', 'Y' );
	}

	protected function run() {
		$scheduler = $this->queueScheduler();
		if ( $this->isSyncAvailable() || $scheduler->hasScheduledEvent() ) {
			$scheduler->setup();
		}
		$this->ensureSitesRegistryImported();
		if ( $this->isSyncEnabled() ) {
			$this->setupHooks();
		}
		$this->setupCronHooks();
	}

	private function setupHooks() {
		( new NotifyWhitelist() )->execute();

		add_action( 'shield/plugin_activated', fn() => $this->importFromFlag() );

		if ( !empty( $this->getImportExportMasterImportUrl() ) ) {
			// For auto update whitelist notifications:
			add_action(
				self::con()->prefix( Actions\PluginImportExport_UpdateNotified::SLUG ),
				fn() => ( new Import() )->autoImportFromMaster()
			);
		}
	}

	public function isSyncAvailable() :bool {
		try {
			return self::con()->caps->canImportExportSync();
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}

	public function isSyncEnabled() :bool {
		return $this->isSyncAvailable() && self::con()->opts->optIs( 'importexport_enable', 'Y' );
	}

	public function syncSitesState() :string {
		if ( !$this->isSyncAvailable() ) {
			return self::SYNC_STATE_UNAVAILABLE;
		}
		return self::con()->opts->optIs( 'importexport_enable', 'Y' ) ? self::SYNC_STATE_ENABLED : self::SYNC_STATE_DISABLED;
	}

	public function ensureSitesRegistryImported( bool $includeOldQueueState = true ) :void {
		try {
			( new SiteRepository() )->ensureLegacyImported( $includeOldQueueState );
		}
		catch ( \Throwable $e ) {
		}
	}

	public function scheduleQueueSoonIfSyncEnabled( int $delay = 30 ) :void {
		if ( $this->isSyncAvailable() ) {
			$this->queueScheduler()->scheduleSoon( $delay );
		}
	}

	public function refreshRegistryAndScheduleQueueIfEnabled( bool $includeOldQueueState = true ) :void {
		$this->ensureSitesRegistryImported( $includeOldQueueState );
		$this->scheduleQueueSoonIfSyncEnabled();
	}

	public function enableAutomaticImportExport() :void {
		$this->assertSyncAvailable();
		self::con()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$this->refreshRegistryAndScheduleQueueIfEnabled();
	}

	public function queueSitesForSync( array $ids ) :int {
		$this->assertSyncEnabled();

		$ids = \array_values( \array_unique( \array_filter(
			\array_map( 'intval', $ids ),
			static fn( int $id ) :bool => $id > 0
		) ) );
		$count = ( new SiteRepository() )->queueSiteIds( $ids );
		if ( $count > 0 ) {
			$this->scheduleQueueSoonIfSyncEnabled();
		}
		return $count;
	}

	public function queueAllActiveSitesForSync() :int {
		$this->assertSyncEnabled();

		$this->ensureSitesRegistryImported();
		$count = ( new SiteRepository() )->queueAllActive();
		if ( $count > 0 ) {
			$this->scheduleQueueSoonIfSyncEnabled();
		}
		return $count;
	}

	/**
	 * @return array{
	 *     authorised_urls:string[],
	 *     already_authorised_urls:string[],
	 *     authorised_count:int,
	 *     already_authorised_count:int,
	 *     total_count:int
	 * }
	 */
	public function authoriseUrlsForSyncSites( array $rawUrls ) :array {
		$this->assertSyncEnabled();
		$this->ensureSitesRegistryImported();

		$repo = new SiteRepository();
		$validUrls = [];
		$invalidUrls = [];
		foreach ( $rawUrls as $rawUrl ) {
			$rawUrl = \trim( (string)$rawUrl );
			if ( $rawUrl === '' ) {
				continue;
			}

			$url = $repo->canonicalizeUrl( $rawUrl );
			if ( $url === '' ) {
				$invalidUrls[] = $rawUrl;
				continue;
			}

			$validUrls[] = $url;
		}

		$validUrls = \array_values( \array_unique( $validUrls ) );
		$invalidUrls = \array_values( \array_unique( $invalidUrls ) );
		if ( !empty( $invalidUrls ) ) {
			throw new \RuntimeException( sprintf(
				_n(
					'%s URL is invalid. Please provide HTTP or HTTPS URLs only.',
					'%s URLs are invalid. Please provide HTTP or HTTPS URLs only.',
					\count( $invalidUrls ),
					'wp-simple-firewall'
				),
				\count( $invalidUrls )
			) );
		}
		if ( empty( $validUrls ) ) {
			throw new \RuntimeException( __( 'Please provide at least one valid URL.', 'wp-simple-firewall' ) );
		}

		$activeRowsBefore = $repo->findByUrls( $validUrls );
		$authorisedUrls = [];
		$alreadyAuthorisedUrls = [];
		foreach ( $validUrls as $url ) {
			if ( isset( $activeRowsBefore[ $url ] ) ) {
				$alreadyAuthorisedUrls[] = $url;
				continue;
			}

			if ( !$repo->upsertActive( $url, ImportExportSitesDB::SOURCE_MANUAL, '', true ) ) {
				throw new \RuntimeException( __( 'The site URL could not be authorised.', 'wp-simple-firewall' ) );
			}
			$authorisedUrls[] = $url;
		}

		$repo->syncFallbackSettings();
		if ( !empty( $authorisedUrls ) ) {
			$this->scheduleQueueSoonIfSyncEnabled();
		}

		return [
			'authorised_urls'          => $authorisedUrls,
			'already_authorised_urls'  => $alreadyAuthorisedUrls,
			'authorised_count'         => \count( $authorisedUrls ),
			'already_authorised_count' => \count( $alreadyAuthorisedUrls ),
			'total_count'              => \count( $validUrls ),
		];
	}

	public function addUrlToImportExportWhitelistUrls( string $url ) {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			self::con()
				->opts
				->optSet(
					'importexport_whitelist', \array_unique( \array_merge( $this->getImportExportWhitelist(), [ $url ] ) )
				)
				->store();

			try {
				$repo = new SiteRepository();
				$repo->upsertActive( $url, ImportExportSitesDB::SOURCE_MANUAL, '', true );
				$repo->syncFallbackSettings();
			}
			catch ( \Throwable $e ) {
			}
		}
	}

	public function removeUrlFromImportExportWhitelistUrls( string $url ) {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			self::con()
				->opts
				->optSet( 'importexport_whitelist', \array_diff( $this->getImportExportWhitelist(), [ $url ] ) )
				->store();

			try {
				( new SiteRepository() )->softDeleteUrl( $url );
			}
			catch ( \Throwable $e ) {
			}
		}
	}

	public function getImportExportMasterImportUrl(): string {
		return self::con()->opts->optGet( 'importexport_masterurl' );
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist(): array {
		return self::con()->opts->optGet( 'importexport_whitelist' );
	}

	public function getImportExportSecretKey(): string {
		$opts = self::con()->opts;
		$ID = $opts->optGet( 'importexport_secretkey' );
		if ( empty( $ID ) || Services::Request()->ts() > $opts->optGet( 'importexport_secretkey_expires_at' ) ) {
			$ID = \hash( 'sha1', ( new InstallationID() )->id().wp_rand( 0, \PHP_INT_MAX ) );
			$opts->optSet( 'importexport_secretkey', $ID )
			     ->optSet( 'importexport_secretkey_expires_at', Services::Request()->ts() + \DAY_IN_SECONDS );
		}
		return $ID;
	}

	public function verifySecretKey( string $secret ): bool {
		return !empty( $secret ) && $this->getImportExportSecretKey() == $secret;
	}

	private function importFromFlag() {
		try {
			( new Import() )->fromFile( self::con()->paths->forFlag( 'import.json' ) );
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * We've been notified that there's an update to pull in from the master site, so we set a cron to do this.
	 */
	public function runOptionsUpdateNotified() {
		$con = self::con();
		if ( $this->isSyncEnabled() && !empty( $this->getImportExportMasterImportUrl() ) ) {
			$cronHook = $con->prefix( Actions\PluginImportExport_UpdateNotified::SLUG );
			if ( !wp_next_scheduled( $cronHook ) ) {
				wp_schedule_single_event( Services::Request()->ts() + \wp_rand( 30, 180 ), $cronHook );
				$con->comps->events->fireEvent( 'import_notify_received', [
					'audit_params' => [
						'master_site' => $con->opts->optGet( 'importexport_masterurl' )
					]
				] );
			}
		}
	}

	public function runDailyCron() {
		if ( $this->isSyncEnabled() ) {
			( new Import() )->autoImportFromMaster();
		}
	}

	private function queueScheduler() :QueueScheduler {
		return new QueueScheduler( fn() :bool => $this->isSyncEnabled() );
	}

	private function assertSyncAvailable() :void {
		if ( !$this->isSyncAvailable() ) {
			throw new \RuntimeException( __( 'Import/export sync is not available on this plan.', 'wp-simple-firewall' ) );
		}
	}

	private function assertSyncEnabled() :void {
		$this->assertSyncAvailable();
		if ( !self::con()->opts->optIs( 'importexport_enable', 'Y' ) ) {
			throw new \RuntimeException( __( 'Import and export is not enabled.', 'wp-simple-firewall' ) );
		}
	}
}
