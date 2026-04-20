<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Update {

	use PluginControllerConsumer;
	use ScanControllerConsumer;

	public function clearIgnored() {
		$this->clearIgnoredWithinScope();
	}

	public function clearIgnoredWithinScope( string $assetType = '', string $assetKey = '' ) {
		$wheres = [
			sprintf( "`scan`='%s'", $this->getScanController()->getSlug() ),
			"`resolved_at`=0",
		];
		if ( $assetType !== '' && $assetKey !== '' ) {
			$wheres[] = sprintf( "`asset_type`='%s'", esc_sql( $assetType ) );
			$wheres[] = sprintf( "`asset_key`='%s'", esc_sql( $assetKey ) );
		}

		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s`
						SET `ignored_at`=0,
							`updated_at`=%d
						WHERE %s",
				self::con()->db_con->scan_result_items->getTable(),
				Services::Request()->ts(),
				\implode( ' AND ', $wheres )
			)
		);
	}
}
