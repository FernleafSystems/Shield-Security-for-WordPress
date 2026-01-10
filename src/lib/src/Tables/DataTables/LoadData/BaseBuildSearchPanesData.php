<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BaseBuildSearchPanesData {

	use PluginControllerConsumer;

	public function build() :array {
		return [];
	}
}