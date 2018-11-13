<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Scanner', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_HackProtect_Scanner extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * ICWP_WPSF_Processor_HackProtect_Scanner constructor.
	 * @param $oModCon
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
		if ( $oFO->isWpvulnEnabled() ) {
			$this->getSubProcessorVuln()->run();
		}
		if ( $oFO->isPtgEnabled() ) {
//			$this->getSubProcessorPtg()->run();
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
			require_once( dirname( __FILE__ ).'/hackprotect_scan_ptg.php' );
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
			require_once( dirname( __FILE__ ).'/hackprotect_integrity.php' );
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
			require_once( dirname( __FILE__ ).'/hackprotect_scan_ufc.php' );
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
			require_once( dirname( __FILE__ ).'/hackprotect_scan_wcf.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_Wcf( $this->getMod() ) )
				->setScannerDb( $this );
			$this->aSubPros[ 'wcf' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_WpVulnScan
	 */
	protected function getSubProcessorVuln() {
		$oProc = $this->getSubPro( 'vuln' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/hackprotect_wpvulnscan.php' );
			$oProc = ( new ICWP_WPSF_Processor_HackProtect_WpVulnScan( $this->getMod() ) );
//				->setScannerDb( $this->getSubProcessorScanner() );
			$this->aSubPros[ 'vuln' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO
	 */
	protected function getEntryVo() {
		return new \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO();
	}

	/**
	 * @return ICWP_WPSF_Query_Scanner_Delete
	 */
	public function getQueryDeleter() {
		$this->queryRequireLib( 'delete.php' );
		$oQ = new ICWP_WPSF_Query_Scanner_Delete();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Scanner_Insert
	 */
	public function getQueryInserter() {
		$this->queryRequireLib( 'insert.php' );
		$oQ = new ICWP_WPSF_Query_Scanner_Insert();
		return $oQ->setTable( $this->getTableName() );
	}
	/**
	 * @return ICWP_WPSF_Query_Scanner_Select
	 */
	public function getQuerySelector() {
		$this->queryRequireLib( 'select.php' );
		return ( new ICWP_WPSF_Query_Scanner_Select() )
			->setResultsAsVo( true )
			->setTable( $this->getTableName() );
	}


	/**
	 * @return ICWP_WPSF_Query_Scanner_Update
	 */
	public function getQueryUpdater() {
		$this->queryRequireLib( 'update.php' );
		return ( new ICWP_WPSF_Query_Scanner_Update() )->setTable( $this->getTableName() );
	}

	/**
	 * @return string
	 */
	protected function queryGetDir() {
		return path_join( parent::queryGetDir(), 'scanner/' );
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			hash varchar(32) NOT NULL DEFAULT '' COMMENT 'Unique Item Hash',
			data text COMMENT 'Relevant Item Data',
			description text COMMENT 'Human Description',
			scan varchar(10) NOT NULL DEFAULT 0 COMMENT 'Scan Type',
			severity int(3) NOT NULL DEFAULT 1 COMMENT 'Severity',
			ignore_until int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Ignore Expires',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Discovered',
			updated_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Last Scan',
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'table_columns_scanner' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}
}