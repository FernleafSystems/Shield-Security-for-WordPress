<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

	public function mod() :ModCon {
		return $this->con()->getModule_HackGuard();
	}

	public function opts() :Options {
		return $this->mod()->getOptions();
	}

	/**
	 * @return ModCon
	 * @deprecated 17.1
	 */
	public function getMod() {
		return $this->mod();
	}

	/**
	 * @return Options
	 * @deprecated 17.1
	 */
	public function getOptions() {
		return $this->opts();
	}

	/**
	 * @return $this
	 * @deprecated 17.1
	 */
	public function setMod( $null ) {
		return $this;
	}
}