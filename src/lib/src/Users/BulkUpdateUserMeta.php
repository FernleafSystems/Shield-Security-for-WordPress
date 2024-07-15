<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BulkUpdateUserMeta {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()
			->db_con
			->user_meta
			->isReady();
	}

	protected function run() {
		$WPDB = Services::WpDb();
		/** @var array[] $IDs */
		$IDs = $WPDB->selectCustom( sprintf(
			'SELECT `ID` from `%s` WHERE `ID` NOT IN (%s) LIMIT 20',
			$WPDB->getTable_Users(),
			$this->getExistingUserMetaIDsQuery()
		) );

		\array_map(
			function ( $ID ) {
				if ( \is_array( $ID ) && !empty( $ID[ 'ID' ] ) ) {
					$user = Services::WpUsers()->getUserById( $ID[ 'ID' ] );
					self::con()->user_metas->for( $user );
				}
			},
			\is_array( $IDs ) ? $IDs : []
		);
	}

	private function getExistingUserMetaIDsQuery() :string {
		return self::con()
			->db_con
			->user_meta
			->getQuerySelector()
			->setResultsAsVo( false )
			->setSelectResultsFormat( ARRAY_A )
			->setColumnsToSelect( [ 'user_id' ] )
			->buildQuery();
	}
}