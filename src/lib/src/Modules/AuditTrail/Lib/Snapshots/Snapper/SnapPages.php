<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

class SnapPages extends BaseSnapPosts {

	protected function retrieve( array $params = [] ) :array {
		$items = parent::retrieve( $params );

		$blogId = (int)get_option( 'page_for_posts' );
		$frontId = (int)get_option( 'page_on_front' );
		foreach ( $items as &$item ) {
			$item[ 'is_blog' ] = $blogId == $item[ 'uniq' ];
			$item[ 'is_front' ] = $frontId == $item[ 'uniq' ];
		}

		return $items;
	}

	protected function getBaseParameters() :array {
		$params = parent::getBaseParameters();
		$params[ 'post_type' ] = 'page';
		return $params;
	}
}