<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Is there a more efficient way to do this?
 */
class ReorderCustomRules {

	use PluginControllerConsumer;

	public function run( array $newOrder ) {
		$position = 1;
		foreach ( $newOrder as $recordID ) {
			self::con()->db_con->rules->getQueryUpdater()->updateById( (int)$recordID, [
				'exec_order' => $position++,
			] );
		}
	}
}