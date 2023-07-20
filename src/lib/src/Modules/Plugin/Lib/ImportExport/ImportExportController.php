<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportController {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	protected function canRun() :bool {
		return $this->opts()->isOpt( 'importexport_enable', 'Y' );
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
			add_action( $this->con()->prefix( Actions\PluginImportExport_UpdateNotified::SLUG ), function () {
				( new Import() )->autoImportFromMaster();
			} );
		}
	}

	public function addUrlToImportExportWhitelistUrls( string $url ) {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			$urls = $this->opts()->getImportExportWhitelist();
			$urls[] = $url;
			$this->opts()->setOpt( 'importexport_whitelist', $urls );
			$this->mod()->saveModOptions();
		}
	}

	public function removeUrlFromImportExportWhitelistUrls( string $url ) {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			$urls = $this->opts()->getImportExportWhitelist();
			$key = \array_search( $url, $urls );
			if ( $key !== false ) {
				unset( $urls[ $key ] );
			}
			$this->opts()->setOpt( 'importexport_whitelist', $urls );
			$this->mod()->saveModOptions();
		}
	}

	protected function getImportExportSecretKey() :string {
		$ID = $this->opts()->getOpt( 'importexport_secretkey', '' );
		if ( empty( $ID ) || $this->isImportExportSecretKeyExpired() ) {
			$ID = \sha1( $this->con()->getInstallationID()[ 'id' ].wp_rand( 0, PHP_INT_MAX ) );
			$this->opts()
				 ->setOpt( 'importexport_secretkey', $ID )
				 ->setOpt( 'importexport_secretkey_expires_at', Services::Request()->ts() + \DAY_IN_SECONDS );
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
			( new Import() )->fromFile( $this->con()->paths->forFlag( 'import.json' ) );
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * We've been notified that there's an update to pull in from the master site, so we set a cron to do this.
	 */
	public function runOptionsUpdateNotified() {
		$cronHook = $this->con()->prefix( Actions\PluginImportExport_UpdateNotified::SLUG );
		if ( !wp_next_scheduled( $cronHook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + \rand( 30, 180 ), $cronHook );
			$this->con()->fireEvent(
				'import_notify_received',
				[ 'audit_params' => [ 'master_site' => $this->opts()->getImportExportMasterImportUrl() ] ]
			);
		}
	}

	public function runDailyCron() {
		( new Import() )->autoImportFromMaster();
	}
}