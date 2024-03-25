<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;

/**
 * @deprecated 19.1
 */
trait ModConsumer {

	use \FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

	public function mod() :ModCon {
		return self::con()->modules[ EnumModules::COMMENTS ];
	}

	public function opts() :Options {
		return $this->mod()->opts();
	}
}