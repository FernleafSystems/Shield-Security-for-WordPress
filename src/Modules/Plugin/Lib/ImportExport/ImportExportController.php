<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as ImportExportSitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\QueueScheduler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SyncSiteUrlValidator;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportController {
	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	public const SYNC_STATE_UNAVAILABLE = 'unavailable';
	public const SYNC_STATE_DISABLED = 'disabled';
	public const SYNC_STATE_ENABLED = 'enabled';
	public const UPDATE_NOTIFY_COOLDOWN = 300;

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
			// For auto update sync notifications:
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

	public function networkSyncState() :string {
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

	public function setAutomaticImportExportEnabled( bool $enabled ) :void {
		if ( $enabled ) {
			$this->enableAutomaticImportExport();
		}
		else {
			$this->assertSyncAvailable();
			self::con()->opts->optSet( 'importexport_enable', 'N' )->store();
		}
	}

	public function disconnectMasterSite() :void {
		$this->assertSyncAvailable();
		self::con()->opts->optSet( 'importexport_masterurl', '' )->store();
	}

	public function queueSitesForSync( array $ids ) :int {
		$this->assertSyncEnabled();

		$count = ( new SiteRepository() )->queueSiteIds( $ids );
		if ( $count > 0 ) {
			$this->scheduleQueueSoonIfSyncEnabled();
		}
		return $count;
	}

	public function deleteSitesById( array $ids ) :int {
		$this->assertSyncEnabled();

		return ( new SiteRepository() )->deleteByIds( $ids );
	}

	public function repairSitesById( array $ids ) :int {
		$this->assertSyncEnabled();

		$count = ( new SiteRepository() )->repairConnectionsByIds( $ids );
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
	public function authoriseUrlsForSyncSites( array $rawUrls, bool $sendInvites = true ) :array {
		$this->assertSyncEnabled();
		$this->ensureSitesRegistryImported();

		$repo = new SiteRepository();
		$validator = new SyncSiteUrlValidator();
		$validUrls = [];
		$invalidUrls = [];
		foreach ( $rawUrls as $rawUrl ) {
			$rawUrl = \trim( (string)$rawUrl );
			if ( $rawUrl === '' ) {
				continue;
			}

			try {
				$url = $validator->validateTrustedSyncUrl( $rawUrl );
			}
			catch ( \Throwable $e ) {
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
					'%s URL is invalid. Please provide normal HTTP or HTTPS site URLs only; localhost and private IP addresses are not allowed.',
					'%s URLs are invalid. Please provide normal HTTP or HTTPS site URLs only; localhost and private IP addresses are not allowed.',
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
				( new ProfileRepository() )->resolveProfileRefForSite( $activeRowsBefore[ $url ] );
				$alreadyAuthorisedUrls[] = $url;
				continue;
			}

			if ( !$repo->upsertPendingClientSite( $url, ImportExportSitesDB::SOURCE_MANUAL, $sendInvites ) ) {
				throw new \RuntimeException( __( 'The site URL could not be authorised.', 'wp-simple-firewall' ) );
			}
			$authorisedUrls[] = $url;
		}

		if ( !empty( $authorisedUrls ) ) {
			( new NetworkInviteRepository() )->clearAll();
			if ( $sendInvites ) {
				$this->scheduleQueueSoonIfSyncEnabled();
			}
		}

		return [
			'authorised_urls'          => $authorisedUrls,
			'already_authorised_urls'  => $alreadyAuthorisedUrls,
			'authorised_count'         => \count( $authorisedUrls ),
			'already_authorised_count' => \count( $alreadyAuthorisedUrls ),
			'total_count'              => \count( $validUrls ),
		];
	}

	public function getImportExportMasterImportUrl(): string {
		return self::con()->opts->optGet( 'importexport_masterurl' );
	}

	public function addSyncSiteExportUrl( string $url, string $importID = '' ) :void {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			if ( ( new SiteRepository() )->upsertActive( $url, ImportExportSitesDB::SOURCE_EXPORT, $importID, true ) ) {
				( new NetworkInviteRepository() )->clearAll();
			}
		}
	}

	public function removeSyncSiteExportUrl( string $url ) :void {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			( new SiteRepository() )->softDeleteUrl( $url );
		}
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
		$stored = $this->getImportExportSecretKey();
		return $secret !== '' && $stored !== '' && \hash_equals( $stored, $secret );
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
	public function runOptionsUpdateNotified( string $notifyingMasterUrl = '', string $notifyingImportId = '' ) :bool {
		$con = self::con();
		if ( !$this->isSyncAvailable() ) {
			return false;
		}
		if ( !$con->opts->optIs( 'importexport_enable', 'Y' ) ) {
			return false;
		}
		$masterUrl = $this->getImportExportMasterImportUrl();
		if ( empty( $masterUrl ) ) {
			return false;
		}
		if ( !$this->notifyingMasterMatchesConfiguredMaster( $notifyingMasterUrl, $masterUrl ) ) {
			return false;
		}
		if ( !$this->notifyingImportIdAllowed( $notifyingImportId ) ) {
			return false;
		}
		if ( $this->notifyCooldownActive( $masterUrl ) ) {
			return false;
		}

		$cronHook = $con->prefix( Actions\PluginImportExport_UpdateNotified::SLUG );
		$now = Services::Request()->ts();
		$nextScheduled = wp_next_scheduled( $cronHook );
		if ( $nextScheduled !== false && (int)$nextScheduled <= $now ) {
			$this->setNotifyCooldown( $masterUrl );
			$this->spawnCron( $now );
			return true;
		}
		if ( $nextScheduled !== false ) {
			wp_clear_scheduled_hook( $cronHook );
		}
		if ( !wp_schedule_single_event( $now, $cronHook ) ) {
			return false;
		}
		$this->setNotifyCooldown( $masterUrl );

		$con->comps->events->fireEvent( 'import_notify_received', [
			'audit_params' => [
				'master_site' => $masterUrl,
			],
		] );

		$this->spawnCron( $now );
		return true;
	}

	public function runDailyCron() {
		if ( $this->isSyncEnabled() ) {
			( new Import() )->autoImportFromMaster();
		}
	}

	private function queueScheduler() :QueueScheduler {
		return new QueueScheduler( fn() :bool => $this->isSyncEnabled() );
	}

	private function notifyingMasterMatchesConfiguredMaster( string $notifyingMasterUrl, string $configuredMasterUrl ) :bool {
		$notifyingMasterUrl = \trim( $notifyingMasterUrl );
		if ( $notifyingMasterUrl === '' ) {
			return true;
		}

		$data = Services::Data();
		$notifyingMasterUrl = $data->validateSimpleHttpUrl( $notifyingMasterUrl );
		$configuredMasterUrl = $data->validateSimpleHttpUrl( $configuredMasterUrl );
		return $notifyingMasterUrl !== false
			   && $configuredMasterUrl !== false
			   && \strcasecmp( (string)$notifyingMasterUrl, (string)$configuredMasterUrl ) === 0;
	}

	private function notifyingImportIdAllowed( string $notifyingImportId ) :bool {
		$notifyingImportId = \trim( $notifyingImportId );
		$localImportId = \trim( (string)self::con()->opts->optGet( 'import_id' ) );

		return $localImportId === ''
			   || $notifyingImportId === ''
			   || \hash_equals( $localImportId, $notifyingImportId );
	}

	private function notifyCooldownActive( string $masterUrl ) :bool {
		return \get_transient( $this->notifyCooldownKey( $masterUrl ) ) !== false;
	}

	private function setNotifyCooldown( string $masterUrl ) :void {
		\set_transient( $this->notifyCooldownKey( $masterUrl ), 1, self::UPDATE_NOTIFY_COOLDOWN );
	}

	private function notifyCooldownKey( string $masterUrl ) :string {
		return self::con()->prefix( 'importexport_updatenotified_' ).\hash( 'sha256', \strtolower( \trim( $masterUrl ) ) );
	}

	private function spawnCron( int $timestamp ) :void {
		if ( \function_exists( 'spawn_cron' ) ) {
			\spawn_cron( $timestamp );
		}
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
