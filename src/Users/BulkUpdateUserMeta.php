<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BulkUpdateUserMeta {
	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun(): bool {
		return self::con()
			->db_con
			->user_meta
			->isReady();
	}

	protected function run() {
		$WPDB = Services::WpDb();
		$IDs = $WPDB->selectCustom( sprintf(
			'SELECT `ID` from `%s` WHERE `ID` NOT IN (%s) LIMIT 20',
			$WPDB->getTable_Users(),
			$this->getExistingUserMetaIDsQuery()
		) );

		\array_map(
			static fn( array $ID ) => self::con()->user_metas->for( Services::WpUsers()->getUserById( $ID[ 'ID' ] ) ),
			\array_filter(
				\is_array( $IDs ) ? $IDs : [],
				static fn( $id ) => \is_array( $id ) && !empty( $id[ 'ID' ] )
			)
		);
	}

	private function getExistingUserMetaIDsQuery(): string {
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