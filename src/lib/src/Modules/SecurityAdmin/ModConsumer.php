<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

	public function mod() :ModCon {
		return $this->con()->getModule_SecAdmin();
	}

	public function opts() :Options {
		return $this->mod()->getOptions();
	}

	/**
	 * @return ModCon
	 * @deprecated 18.1
	 */
	public function getMod() {
		return $this->mod();
	}

	/**
	 * @return Options
	 * @deprecated 18.1
	 */
	public function getOptions() {
		return $this->opts();
	}
}