<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Services\Services;

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
		if ( $oFO->isApcEnabled() ) {
			$this->getSubProcessorApc()->run();
		}
		if ( $oFO->isIcEnabled() ) {
//			$this->getSubProcessorIntegrity()->run();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Apc|mixed
	 */
	public function getSubProcessorApc() {
		return $this->getSubPro( 'apc' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ufc|mixed
	 */
	protected function getSubProcessorIntegrity() {
		return $this->getSubPro( 'int' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ptg|mixed
	 */
	public function getSubProcessorPtg() {
		return $this->getSubPro( 'ptg' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ufc|mixed
	 */
	public function getSubProcessorUfc() {
		return $this->getSubPro( 'ufc' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Wcf|mixed
	 */
	public function getSubProcessorWcf() {
		return $this->getSubPro( 'wcf' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Wpv|mixed
	 */
	public function getSubProcessorWpv() {
		return $this->getSubPro( 'wpv' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'apc' => 'ICWP_WPSF_Processor_HackProtect_Apc',
			'int' => 'ICWP_WPSF_Processor_HackProtect_Integrity',
			'ptg' => 'ICWP_WPSF_Processor_HackProtect_Ptg',
			'ufc' => 'ICWP_WPSF_Processor_HackProtect_Ufc',
			'wpv' => 'ICWP_WPSF_Processor_HackProtect_Wpv',
			'wcf' => 'ICWP_WPSF_Processor_HackProtect_Wcf',
		];
	}

	/**
	 * @param string $sKey
	 * @return ICWP_WPSF_Processor_ScanBase|null
	 */
	protected function getSubPro( $sKey ) {
		/** @var ICWP_WPSF_Processor_ScanBase $oPro */
		$oPro = parent::getSubPro( $sKey );
		return $oPro->setScannerDb( $this );
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