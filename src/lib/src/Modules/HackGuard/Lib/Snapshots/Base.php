<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

class Base {

	/**
	 * @var string
	 */
	private $sContext;

	/**
	 * @var string
	 */
	protected $sStorePath;

	/**
	 * @return string
	 */
	public function getContext() {
		return $this->sContext;
	}

	/**
	 * @return string
	 */
	protected function getSnapStorePath() {
		return path_join( $this->getStorePath(), $this->getContext().'.txt' );
	}

	/**
	 * @return string
	 */
	public function getStorePath() {
		return $this->sStorePath;
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function setContext( $sContext ) {
		$this->sContext = $sContext;
		return $this;
	}

	/**
	 * @param string $sTmpDir
	 * @return $this
	 */
	public function setStorePath( $sTmpDir ) {
		$this->sStorePath = $sTmpDir;
		return $this;
	}


}