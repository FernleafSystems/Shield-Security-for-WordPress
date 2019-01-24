<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

class ICWP_WPSF_Processor_HackProtect_Scanner extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * ICWP_WPSF_Processor_HackProtect_Scanner constructor.
	 * @param ICWP_WPSF_FeatureHandler_HackProtect $oModCon
	 */
	public function __construct( $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'table_name_scanner' ) );
	}

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isWcfScanEnabled() ) {
			$this->getSubProcessorWcf()->run();
		}
		if ( $oFO->isUfcEnabled() ) {
			$this->getSubProcessorUfc()->run();
		}
		if ( $oFO->isPtgEnabled() ) {
			$this->getSubProcessorPtg()->run();
		}
		if ( $oFO->isWpvulnEnabled() ) {
			$this->getSubProcessorWpv()->run();
		}
		if ( $oFO->isIcEnabled() ) {
//			$this->getSubProcessorIntegrity()->run();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ptg
	 */
	public function getSubProcessorPtg() {
		$oProc = $this->getSubPro( 'ptg' );
		if ( is_null( $oProc ) ) {
			require_once( __DIR__.'/hackprotect_scan_ptg.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Ptg( $this->getMod() ) )
				->setScannerDb( $this );
			$this->aSubPros[ 'ptg' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Integrity
	 */
	protected function getSubProcessorIntegrity() {
		$oProc = $this->getSubPro( 'int' );
		if ( is_null( $oProc ) ) {
			require_once( __DIR__.'/hackprotect_integrity.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Integrity( $this->getMod() ) );
//				->setScannerDb( $this );
			$this->aSubPros[ 'int' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ufc
	 */
	public function getSubProcessorUfc() {
		$oProc = $this->getSubPro( 'ufc' );
		if ( is_null( $oProc ) ) {
			require_once( __DIR__.'/hackprotect_scan_ufc.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Ufc( $this->getMod() ) )
				->setScannerDb( $this );
			$this->aSubPros[ 'ufc' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Wcf
	 */
	public function getSubProcessorWcf() {
		$oProc = $this->getSubPro( 'wcf' );
		if ( is_null( $oProc ) ) {
			require_once( __DIR__.'/hackprotect_scan_wcf.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Wcf( $this->getMod() ) )
				->setScannerDb( $this );
			$this->aSubPros[ 'wcf' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Wpv
	 */
	public function getSubProcessorWpv() {
		$oProc = $this->getSubPro( 'wpv' );
		if ( is_null( $oProc ) ) {
			require_once( __DIR__.'/hackprotect_scan_wpv.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Wpv( $this->getMod() ) )
				->setScannerDb( $this );
			$this->aSubPros[ 'wpv' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\Handler
	 */
	protected function createDbHandler() {
		return new \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\Handler();
	}

	/**
	 * Based on the Ajax Download File pathway (hence the cookie)
	 * @param string $sItemId
	 */
	public function downloadItemFile( $sItemId ) {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oEntry */
		$oEntry = $this->getDbHandler()
					   ->getQuerySelector()
					   ->byId( (int)$sItemId );
		if ( !empty( $oEntry ) ) {
			$sPath = $oEntry->meta[ 'path_full' ];
			$oFs = $this->loadFS();
			if ( $oFs->isFile( $sPath ) ) {
				header( 'Set-Cookie: fileDownload=true; path=/' );
				$this->loadRequest()
					 ->downloadStringAsFile( $oFs->getFileContent( $sPath ), basename( $sPath ) );
			}
		}

		wp_die( "Something about this request wasn't right" );
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			hash varchar(32) NOT NULL DEFAULT '' COMMENT 'Unique Item Hash',
			meta text COMMENT 'Relevant Item Data',
			scan varchar(10) NOT NULL DEFAULT 0 COMMENT 'Scan Type',
			severity int(3) NOT NULL DEFAULT 1 COMMENT 'Severity',
			ignored_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Ignored',
			notified_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Last Notified',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Discovered',
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Soft Deleted',
			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'table_columns_scanner' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @deprecated
	 * @return Scanner\Delete
	 */
	public function getQueryDeleter() {
		return parent::getQueryDeleter();
	}

	/**
	 * @deprecated
	 * @return Scanner\Insert
	 */
	public function getQueryInserter() {
		return parent::getQueryInserter();
	}

	/**
	 * @deprecated
	 * @return Scanner\Select
	 */
	public function getQuerySelector() {
		return parent::getQuerySelector();
	}

	/**
	 * @deprecated
	 * @return Scanner\Update
	 */
	public function getQueryUpdater() {
		return parent::getQueryUpdater();
	}
}