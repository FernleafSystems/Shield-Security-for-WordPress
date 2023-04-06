<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

	public function mod() :ModCon {
		return $this->con()->getModule_AuditTrail();
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
	 * @return $this
	 * @deprecated 17.1
	 */
	public function setMod( $null ) {
		return $this;
	}
}