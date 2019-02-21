<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

/**
 * Class BuildPages
 * @package FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot
 */
class BuildPages extends BuildPosts {

	/**
	 * @param array $aParams
	 * @return array[]
	 */
	protected function retrieve( $aParams = [] ) {
		$aItems = parent::retrieve( $aParams );

		$nBlogId = (int)get_option( 'page_for_posts' );
		$nFrontId = (int)get_option( 'page_on_front' );
		foreach ( $aItems as &$aItem ) {
			$aItem[ 'is_blog' ] = ( $nBlogId == $aItem[ 'uniq' ] );
			$aItem[ 'is_front' ] = ( $nFrontId == $aItem[ 'uniq' ] );
		}

		return $aItems;
	}

	/**
	 * @return array
	 */
	protected function getBaseParameters() {
		$aParams = parent::getBaseParameters();
		$aParams[ 'post_type' ] = 'page';
		return $aParams;
	}
}