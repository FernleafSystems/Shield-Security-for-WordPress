<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as ImportExportSitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportController {
	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	protected function canRun(): bool {
		return self::con()->opts->optIs( 'importexport_enable', 'Y' );
	}

	protected function run() {
		$this->setupHooks();
		$this->setupCronHooks();
	}

	private function setupHooks() {
		try {
			( new SiteRepository() )->ensureLegacyImported();
		}
		catch ( \Throwable $e ) {
		}

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
		if ( $con->opts->optIs( 'importexport_enable', 'Y' ) && !empty( $this->getImportExportMasterImportUrl() ) ) {
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
		( new Import() )->autoImportFromMaster();
	}
}
