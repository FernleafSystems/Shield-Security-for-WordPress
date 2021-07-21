<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDir;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Scan\ScansController
	 */
	private $scanCon;

	/**
	 * @var Scan\Queue\Controller
	 */
	private $scanQueueCon;

	/**
	 * @var Lib\FileLocker\FileLockerController
	 */
	private $oFileLocker;

	protected function doPostConstruction() {
		$this->setCustomCronSchedules();
	}

	public function onWpInit() {
		parent::onWpInit();
		$this->getScanQueueController();
	}

	public function getFileLocker() :Lib\FileLocker\FileLockerController {
		if ( !isset( $this->oFileLocker ) ) {
			$this->oFileLocker = ( new Lib\FileLocker\FileLockerController() )
				->setMod( $this );
		}
		return $this->oFileLocker;
	}

	public function getScansCon() :Scan\ScansController {
		if ( !isset( $this->scanCon ) ) {
			$this->scanCon = ( new Scan\ScansController() )
				->setMod( $this );
		}
		return $this->scanCon;
	}

	public function getScanQueueController() :Scan\Queue\Controller {
		if ( !isset( $this->scanQueueCon ) ) {
			$this->scanQueueCon = ( new Scan\Queue\Controller() )
				->setMod( $this );
		}
		return $this->scanQueueCon;
	}

	/**
	 * @param string $slug
	 * @return Scan\Controller\Base|mixed
	 * @throws \Exception
	 */
	public function getScanCon( string $slug ) {
		return $this->getScansCon()->getScanCon( $slug );
	}

	public function getMainWpData() :array {
		$issues = ( new Lib\Reports\Query\ScanCounts() )->setMod( $this );
		$issues->notified = null;
		return array_merge( parent::getMainWpData(), [
			'scan_issues' => array_filter( $issues->all() )
		] );
	}

	protected function handleFileDownload( string $downloadID ) {
		switch ( $downloadID ) {
			case 'filelocker':
				$this->getFileLocker()->handleFileDownloadRequest();
				break;
			case 'scan_file':
				( new Lib\Utility\FileDownloadHandler() )
					->setDbHandler( $this->getDbHandler_ScanResults() )
					->downloadByItemId( (int)Services::Request()->query( 'rid', 0 ) );
				break;
		}
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$this->cleanFileExclusions();

		if ( $opts->isOptChanged( 'scan_frequency' ) ) {
			$this->getScansCon()->deleteCron();
		}

		if ( count( $opts->getFilesToLock() ) === 0 || !$this->getCon()
															 ->getModule_Plugin()
															 ->getShieldNetApiController()
															 ->canHandshake() ) {
			$opts->setOpt( 'file_locker', [] );
			$this->getFileLocker()->purge();
		}

		$lockFiles = $opts->getFilesToLock();
		if ( in_array( 'root_webconfig', $lockFiles ) && !Services::Data()->isWindows() ) {
			unset( $lockFiles[ array_search( 'root_webconfig', $lockFiles ) ] );
			$opts->setOpt( 'file_locker', $lockFiles );
		}

		foreach ( $this->getScansCon()->getAllScanCons() as $con ) {
			if ( !$con->isEnabled() ) {
				$con->purge();
			}
		}

		$this->cleanPathWhitelist();
	}

	private function cleanPathWhitelist() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt( 'path_whitelist', array_unique( array_filter( array_map(
			function ( $rule ) {
				$rule = wp_normalize_path( strtolower( trim( $rule ) ) );
				if ( !empty( $rule ) ) {
					$toCheck = array_map( 'wp_normalize_path', array_unique( [
						ABSPATH,
						trailingslashit( path_join( ABSPATH, 'wp-admin' ) ),
						trailingslashit( path_join( ABSPATH, 'wp-includes' ) ),
						trailingslashit( WP_CONTENT_DIR ),
						trailingslashit( path_join( WP_CONTENT_DIR, 'plugins' ) ),
						trailingslashit( path_join( WP_CONTENT_DIR, 'themes' ) ),
					] ) );
					$regEx = sprintf(
						'#^%s$#i',
						path_join(
							ABSPATH,
							str_replace( 'WILDCARDSTAR', '.*', preg_quote( str_replace( '*', 'WILDCARDSTAR', $rule ), '#' ) )
						)
					);

					foreach ( $toCheck as $path ) {
						if ( preg_match( $regEx, $path ) ) {
							$rule = false;
							break;
						}
					}
				}
				return $rule;
			},
			$opts->getOpt( 'path_whitelist', [] ) // do not use Options getter as it formats into regex
		) ) ) );
	}

	/**
	 * @return $this
	 */
	protected function setCustomCronSchedules() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$freq = $opts->getScanFrequency();
		Services::WpCron()
				->addNewSchedule(
					$this->prefix( sprintf( 'per-day-%s', $freq ) ),
					[
						'interval' => DAY_IN_SECONDS/$freq,
						'display'  => sprintf( __( '%s per day', 'wp-simple-firewall' ), $freq )
					]
				);
		return $this;
	}

	protected function cleanFileExclusions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$excl = [];

		$toClean = $opts->getOpt( 'ufc_exclusions', [] );
		if ( is_array( $toClean ) ) {
			foreach ( $toClean as $exclusion ) {

				if ( preg_match( '/^#(.+)#$/', $exclusion, $matches ) ) { // it's not regex
					$exclusion = str_replace( '\\', '\\\\', $exclusion );
				}
				else {
					$exclusion = wp_normalize_path( trim( $exclusion ) );
					if ( strpos( $exclusion, '/' ) === false ) { // filename only
						$exclusion = trim( preg_replace( '#[^.0-9a-z_-]#i', '', $exclusion ) );
					}
				}

				if ( !empty( $exclusion ) ) {
					$excl[] = $exclusion;
				}
			}
		}

		$opts->setOpt( 'ufc_exclusions', array_unique( $excl ) );
	}

	public function isPtgEnabled() :bool {
		$opts = $this->getOptions();
		return $this->isModuleEnabled() && $this->isPremium()
			   && $opts->isOpt( 'ptg_enable', 'enabled' )
			   && $opts->isOptReqsMet( 'ptg_enable' )
			   && $this->getCon()->hasCacheDir()
			   && !empty( $this->getPtgSnapsBaseDir() );
	}

	public function getPtgSnapsBaseDir() :string {
		return ( new CacheDir() )
			->setCon( $this->getCon() )
			->buildSubDir( 'ptguard' );
	}

	public function hasWizard() :bool {
		return false;
	}

	public function getScansTempDir() :string {
		return ( new CacheDir() )
			->setCon( $this->getCon() )
			->buildSubDir( 'scans' );
	}

	public function getDbHandler_FileLocker() :Databases\FileLocker\Handler {
		return $this->getDbH( 'filelocker' );
	}

	public function getDbHandler_ScanQueue() :Databases\ScanQueue\Handler {
		return $this->getDbH( 'scanq' );
	}

	public function getDbHandler_ScanResults() :Databases\Scanner\Handler {
		return $this->getDbH( 'scanner' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return ( $this->getDbHandler_ScanQueue() instanceof Databases\ScanQueue\Handler )
			   && $this->getDbHandler_ScanQueue()->isReady()
			   && ( $this->getDbHandler_ScanResults() instanceof Databases\Scanner\Handler )
			   && $this->getDbHandler_ScanResults()->isReady()
			   && parent::isReadyToExecute();
	}

	public function onPluginDeactivate() {
		// 1. Clean out the scanners
		/** @var Options $opts */
		$opts = $this->getOptions();
		foreach ( $opts->getScanSlugs() as $slug ) {
			$this->getScanCon( $slug )->purge();
		}
		$this->getDbHandler_ScanQueue()->tableDelete();
		$this->getDbHandler_ScanResults()->tableDelete();
		// 2. Clean out the file locker
		$this->getFileLocker()->purge();
	}
}