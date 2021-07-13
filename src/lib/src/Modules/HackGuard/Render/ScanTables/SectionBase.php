<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SectionBase {

	use ModConsumer;


	protected function buildRenderData() :array {
		return [];
	}

}