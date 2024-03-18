<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;

/**
 * @deprecated 19.2
 */
trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

	public function mod() :ModCon {
		return self::con()->modules[ EnumModules::SECURITY_ADMIN ];
	}

	public function opts() :Options {
		return $this->mod()->opts();
	}
}