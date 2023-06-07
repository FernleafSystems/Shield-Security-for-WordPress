<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

	public function mod() :ModCon {
		return $this->con()->getModule_Data();
	}

	public function opts() :Options {
		return $this->mod()->getOptions();
	}

	/**
	 * @return $this
	 * @deprecated 18.1
	 */
	public function setMod( $null ) {
		return $this;
	}
}