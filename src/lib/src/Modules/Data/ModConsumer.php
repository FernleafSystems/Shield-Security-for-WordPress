<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

	public function mod() :ModCon {
		return $this->con()->getModule_Data();
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
}