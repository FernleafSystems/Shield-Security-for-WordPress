<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class BuildScanItems {

	use Shield\Modules\ModConsumer;

	abstract public function run() :array;
}