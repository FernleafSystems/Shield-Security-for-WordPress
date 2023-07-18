<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

trait ModConsumer {

	/**
	 * @var Modules\Base\ModCon
	 */
	private $mod;

	/**
	 * @return Controller
	 */
	public function getCon() {
		return $this->mod()->con();
	}

	public static function con() :Controller {
		return shield_security_get_plugin()->getController();
	}

	public function mod() {
		return $this->getMod();
	}

	public function opts() {
		return $this->getOptions();
	}

	/**
	 * @return Base\ModCon|mixed
	 */
	public function getMod() {
		if ( \defined( static::class.'::MOD' ) ) {
			try {
				return shield_security_get_plugin()->getController()->modules[ static::MOD ];
			}
			catch ( \Exception $e ) {
			}
		}
		return $this->mod;
	}

	/**
	 * @return Modules\Base\Options
	 */
	public function getOptions() {
		return $this->mod()->getOptions();
	}

	/**
	 * @param Modules\Base\ModCon $mod
	 * @return $this
	 */
	public function setMod( $mod ) {
		$this->mod = $mod;
		return $this;
	}
}