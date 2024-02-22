<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportController {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'importexport_enable', 'Y' );
	}

	protected function run() {
		$this->setupHooks();
		$this->setupCronHooks();
	}

	private function setupHooks() {
		$this->getImportExportSecretKey();

		( new NotifyWhitelist() )->execute();

		add_action( 'shield/plugin_activated', function () {
			$this->importFromFlag();
		} );

		if ( $this->opts()->hasImportExportMasterImportUrl() ) {
			// For auto update whitelist notifications:
			add_action( self::con()->prefix( Actions\PluginImportExport_UpdateNotified::SLUG ), function () {
				( new Import() )->autoImportFromMaster();
			} );
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
		}
	}

	public function removeUrlFromImportExportWhitelistUrls( string $url ) {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			self::con()
				->opts
				->optSet( 'importexport_whitelist', \array_diff( $this->getImportExportWhitelist(), [ $url ] ) )
				->store();
		}
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() :array {
		return self::con()->opts->optGet( 'importexport_whitelist' );
	}

	protected function getImportExportSecretKey() :string {
		$ID = self::con()->opts->optGet( 'importexport_secretkey' );
		if ( empty( $ID ) || $this->isImportExportSecretKeyExpired() ) {
			$ID = \sha1( ( new InstallationID() )->id().wp_rand( 0, \PHP_INT_MAX ) );
			self::con()
				->opts
				->optSet( 'importexport_secretkey', $ID )
				->optSet( 'importexport_secretkey_expires_at', Services::Request()->ts() + \DAY_IN_SECONDS );
		}
		return $ID;
	}

	public function verifySecretKey( string $secret ) :bool {
		return !empty( $secret ) && $this->getImportExportSecretKey() == $secret;
	}

	protected function isImportExportSecretKeyExpired() :bool {
		return Services::Request()->ts() > $this->opts()->getOpt( 'importexport_secretkey_expires_at' );
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
		// Ensure import/export feature is enabled (for cron and auto-import to run)
		self::con()->opts->optSet( 'importexport_enable', 'Y' );

		$cronHook = self::con()->prefix( Actions\PluginImportExport_UpdateNotified::SLUG );
		if ( !wp_next_scheduled( $cronHook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + \rand( 30, 180 ), $cronHook );
			self::con()->fireEvent(
				'import_notify_received',
				[ 'audit_params' => [ 'master_site' => $this->opts()->getImportExportMasterImportUrl() ] ]
			);
		}
	}

	public function runDailyCron() {
		( new Import() )->autoImportFromMaster();
	}
}