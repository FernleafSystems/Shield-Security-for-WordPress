<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ActionStore {

	use ScanActionConsumer;

	/**
	 * @return array|null
	 */
	public function readActionDefinitionFromDisk() {
		$aDef = null;
		$oFS = Services::WpFs();
		$sPath = $this->getActionFilePath();
		if ( $oFS->exists( $sPath ) ) {
			$sDef = Services::WpFs()->getFileContent( $this->getActionFilePath(), true );
			if ( !empty( $sDef ) ) {
				$aDef = json_decode( $sDef, true );
			}
		}
		return ( !empty( $aDef ) && is_array( $aDef ) ) ? $aDef : null;
	}

	/**
	 * @return $this
	 */
	public function deleteAction() {
		Services::WpFs()->deleteFile( $this->getActionFilePath() );
		return $this;
	}

	/**
	 * @return bool
	 */
	public function storeAction() {
		return Services::WpFs()->putFileContent(
			$this->getActionFilePath(),
			json_encode( $this->getScanActionVO()->getRawDataAsArray() ),
			true
		);
	}

	/**
	 * @return string
	 */
	public function getActionFilePath() {
		return path_join( $this->getScanActionVO()->tmp_dir, 'action-'.$this->getScanActionVO()->id.'.txt' );
	}

	/**
	 * @return string
	 */
	public function getLockFilePath() {
		return path_join( $this->getScanActionVO()->tmp_dir, '.action-'.$this->getScanActionVO()->id.'.lock' );
	}

	/**
	 * @return bool
	 */
	public function isActionRunning() {
		return Services::WpFs()->exists( $this->getActionFilePath() );
	}

	/**
	 * @return bool
	 */
	public function isLocked() {
		return Services::WpFs()->exists( $this->getLockFilePath() );
	}

	/**
	 * @return $this
	 */
	public function lockAction() {
		Services::WpFs()->putFileContent( $this->getLockFilePath(), Services::Request()->ts() );
		return $this;
	}

	/**
	 * @return $this
	 */
	public function unlockAction() {
		Services::WpFs()->deleteFile( $this->getLockFilePath() );
		return $this;
	}
}