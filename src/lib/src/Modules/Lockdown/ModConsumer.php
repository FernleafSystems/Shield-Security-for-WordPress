<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

	public function mod() :ModCon {
		return self::con()->getModule_Lockdown();
	}

	public function opts() :Options {
		return $this->mod()->getOptions();
	}
}