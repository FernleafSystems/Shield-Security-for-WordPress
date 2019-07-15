<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions;

use FernleafSystems\Wordpress\Services\Services;

abstract class Launcher {

	/**
	 * @var ScanActionVO
	 */
	protected $oAction;

	/**
	 * @var string
	 */
	protected $sTmpDir;

	abstract public function run();

	/**
	 * @return ScanActionVO
	 */
	public function readActionDefinition() {
		$aDef = $this->readActionDefinitionFromDisk();
		return $this->getAction()
					->applyFromArray( $aDef );
	}

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
			json_encode( $this->getAction()->getRawDataAsArray() ),
			true
		);
		return $this;
	}

	/**
	 * @return ScanActionVO
	 */
	public function getAction() {
		return $this->oAction;
	}

	/**
	 * @return string
	 */
	protected function getActionFilePath() {
		return path_join( $this->getTmpDir(), 'action-'.$this->getAction()->id.'.txt' );
	}

	/**
	 * @return string
	 */
	protected function getLockFilePath() {
		return path_join( $this->getTmpDir(), '.action-'.$this->getAction()->id.'.lock' );
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
	protected function isActionLocked() {
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
	 * @param ScanActionVO $oAction
	 * @return $this
	 */
	public function setAction( $oAction ) {
		$this->oAction = $oAction;
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