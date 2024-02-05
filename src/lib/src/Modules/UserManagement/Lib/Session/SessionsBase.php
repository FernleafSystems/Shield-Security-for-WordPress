<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\ModConsumer;

class SessionsBase {

	use ModConsumer;

	protected function queryUserMetaForIDs( int $page ) :array {
		// Select the most recently active based on updated Shield User Meta
		/** @var Select $metaSelect */
		$metaSelect = self::con()->db_con->dbhUserMeta()->getQuerySelector();
		$results = $metaSelect->setResultsAsVo( false )
							  ->setSelectResultsFormat( ARRAY_A )
							  ->setColumnsToSelect( [ 'user_id' ] )
							  ->setOrderBy( 'updated_at' )
							  ->setPage( $page )
							  ->setLimit( 200 )
							  ->queryWithResult();
		return \array_map(
			function ( $res ) {
				return (int)$res[ 'user_id' ];
			},
			\is_array( $results ) ? $results : []
		);
	}
}