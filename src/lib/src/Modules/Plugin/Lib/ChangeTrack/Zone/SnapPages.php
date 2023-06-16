<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report\ZoneReportPages;

class SnapPages extends SnapPosts {

	public const SLUG = 'pages';

	public function getZoneReporterClass() :string {
		return ZoneReportPages::class;
	}

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
		$aParams = parent::getBaseParameters();
		$aParams[ 'post_type' ] = 'page';
		return $aParams;
	}
}