<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Services\Services;

class BaseAsyncAction {

	use ScanActionConsumer;

	/**
	 * @var string
	 */
	protected $sTmpDir;

	/**
	 * @return array|null
	 */
	protected function readActionDefinitionFromDisk() {
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
	 * @return $this
	 */
	public function storeAction() {
		Services::WpFs()->putFileContent(
			$this->getActionFilePath(),
			json_encode( $this->getScanActionVO()->getRawDataAsArray() ),
			true
		);
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getActionFilePath() {
		return path_join( $this->getTmpDir(), 'action-'.$this->getScanActionVO()->id.'.txt' );
	}

	/**
	 * @return string
	 */
	protected function getLockFilePath() {
		return path_join( $this->getTmpDir(), '.action-'.$this->getScanActionVO()->id.'.lock' );
	}

	/**
	 * @return string
	 */
	public function getTmpDir() {
		return $this->sTmpDir;
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
	public function isActionLocked() {
		return Services::WpFs()->exists( $this->getLockFilePath() );
	}

	/**
	 * @return $this
	 */
	protected function lockAction() {
		Services::WpFs()->putFileContent( $this->getLockFilePath(), Services::Request()->ts() );
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function unlockAction() {
		Services::WpFs()->deleteFile( $this->getLockFilePath() );
		return $this;
	}

	/**
	 * @param string $sTmpDir
	 * @return $this
	 */
	public function setTmpDir( $sTmpDir ) {
		$this->sTmpDir = $sTmpDir;
		return $this;
	}
}