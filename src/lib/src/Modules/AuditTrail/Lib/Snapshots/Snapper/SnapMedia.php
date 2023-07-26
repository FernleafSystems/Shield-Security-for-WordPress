<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

class SnapMedia extends BaseSnapPosts {

	protected function getBaseParameters() :array {
		$params = parent::getBaseParameters();
		$params[ 'post_type' ] = 'attachment';
		unset( $params[ 'post_status' ] );
		return $params;
	}
}