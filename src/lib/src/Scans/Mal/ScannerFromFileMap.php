<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

/**
 * Class ScannerFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class ScannerFromFileMap extends ScannerBase {

	/**
	 * @var string[]
	 */
	private $aFileMap;

	/**
	 * @return ResultsSet
	 */
	public function run() {
		$oResultSet = new ResultsSet();

		foreach ( $this->getFileMap() as $nKey => $sFullPath ) {
			$oItem = $this->scanPath( $sFullPath );
			if ( $oItem instanceof ResultItem ) {
				$oResultSet->addItem( $oItem );
			}
		}

		return $oResultSet;
	}

	/**
	 * @return string[]
	 */
	public function getFileMap() {
		return is_array( $this->aFileMap ) ? $this->aFileMap : [];
	}

	/**
	 * @param string[] $aSigs
	 * @return $this
	 */
	public function setFileMap( $aSigs ) {
		$this->aFileMap = $aSigs;
		return $this;
	}
}