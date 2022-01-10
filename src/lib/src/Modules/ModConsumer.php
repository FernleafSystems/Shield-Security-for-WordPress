<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

trait ModConsumer {

	/**
	 * @var Modules\Base\ModCon
	 */
	private $oMod;

	/**
	 * @return Controller
	 */
	public function getCon() {
		return $this->getMod()->getCon();
	}

	/**
	 * @return Modules\Base\ModCon
	 */
	public function getMod() {
		return $this->oMod;
	}

	/**
	 * @return Modules\Base\Options
	 */
	public function getOptions() {
		return $this->getMod()->getOptions();
	}

	/**
	 * @param Controller $con
	 * @return $this
	 */
	public function setCon( $con ) {
		$this->getMod()->setCon( $con );
		return $this;
	}

	/**
	 * @param Modules\Base\ModCon $mod
	 * @return $this
	 */
	public function setMod( $mod ) {
		$this->oMod = $mod;
		return $this;
	}
}