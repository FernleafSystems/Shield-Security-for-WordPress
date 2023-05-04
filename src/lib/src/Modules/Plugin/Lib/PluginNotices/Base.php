<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

abstract class Base {

	use ModConsumer;

	abstract public function check() :?array;
}