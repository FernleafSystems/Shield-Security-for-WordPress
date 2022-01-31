<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BulkUpdateUserMeta extends ExecOnceModConsumer {

	use ModConsumer;

	protected function canRun() :bool {
		return $this->getCon()
					->getModule_Data()
					->getDbH_UserMeta()
					->isReady();
	}

	protected function run() {
		$con = $this->getCon();
		$userSearch = new \WP_User_Query( [
			'exclude' => $this->getUserMetaIDs()
		] );
		foreach ( $userSearch->get_results() as $user ) {
			$con->getUserMeta( $user );
		}
	}

	protected function getUserMetaIDs() :array {
		/** @var Select $metaSelect */
		$metaSelect = $this->getCon()
						   ->getModule_Data()
						   ->getDbH_UserMeta()
						   ->getQuerySelector();
		$res = $metaSelect->setResultsAsVo( false )
						  ->setSelectResultsFormat( ARRAY_A )
						  ->setColumnsToSelect( [ 'user_id' ] )
						  ->queryWithResult();
		return array_map(
			function ( $res ) {
				return (int)array_pop( $res );
			},
			is_array( $res ) ? $res : []
		);
	}
}