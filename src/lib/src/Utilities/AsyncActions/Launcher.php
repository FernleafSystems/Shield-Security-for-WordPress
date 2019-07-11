<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions;

use FernleafSystems\Wordpress\Services\Services;

class Launcher {

	/**
	 * @var AsyncActionVO
	 */
	protected $oAction;

	/**
	 * @var string
	 */
	protected $sTmpDir;

	public function storeAction() {
		Services::WpFs()->putFileContent(

		);
	}

	/**
	 * @return string
	 */
	public function getTmpDir() {
		return $this->sTmpDir;
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