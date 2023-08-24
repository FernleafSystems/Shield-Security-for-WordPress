<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_18210() {
		self::con()->getModule_Events()->getMigrator()->dispatch();
	}
}