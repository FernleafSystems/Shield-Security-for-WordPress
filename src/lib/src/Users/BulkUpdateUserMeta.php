<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BulkUpdateUserMeta extends ExecOnceModConsumer {

	use PluginControllerConsumer;

	protected function canRun() :bool {
		return $this->con()
					->getModule_Data()
					->getDbH_UserMeta()
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

		array_map(
			function ( $ID ) {
				if ( is_array( $ID ) && !empty( $ID[ 'ID' ] ) ) {
					$user = Services::WpUsers()->getUserById( $ID[ 'ID' ] );
					$this->con()->user_metas->for( $user );
				}
			},
			is_array( $IDs ) ? $IDs : []
		);
	}

	private function getExistingUserMetaIDsQuery() :string {
		/** @var Select $metaSelect */
		$metaSelect = $this->con()
						   ->getModule_Data()
						   ->getDbH_UserMeta()
						   ->getQuerySelector();
		return $metaSelect->setResultsAsVo( false )
						  ->setSelectResultsFormat( ARRAY_A )
						  ->setColumnsToSelect( [ 'user_id' ] )
						  ->buildQuery();
	}
}