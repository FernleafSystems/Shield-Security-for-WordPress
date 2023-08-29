<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

	public function mod() :ModCon {
		return self::con()->getModule_Data();
	}

	public function opts() :Options {
		return $this->mod()->getOptions();
	}
}