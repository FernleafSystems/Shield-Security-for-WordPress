<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions;

use FernleafSystems\Wordpress\Services\Services;

abstract class Launcher {

	/**
	 * @var AsyncActionVO
	 */
	protected $oAction;

	/**
	 * @var string
	 */
	protected $sTmpDir;

	abstract public function run();

	/**
	 * @return AsyncActionVO
	 */
	public function readAction() {
		return $this->getAction()
					->applyFromArray(
						json_decode(
							Services::WpFs()->getFileContent(
								$this->getActionFilePath(),
								true
							),
							true
						)
					);
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
	 * @return AsyncActionVO
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
	public function getTmpDir() {
		return $this->sTmpDir;
	}

	/**
	 * @param AsyncActionVO $oAction
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