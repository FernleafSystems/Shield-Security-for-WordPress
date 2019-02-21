<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Report\Build;

use FernleafSystems\Wordpress\Services\Services;

class Posts extends Base {

	/**
	 * @param array $aAdded
	 * @return array
	 */
	protected function processAdded( $aAdded ) {
		$aReport = [];
		if ( !empty( $aAdded ) ) {
			$aReport[ 'title' ] = 'Posts Published';
			$aReport[ 'lines' ] = [];
			$oWpPosts = Services::WpPost();
			foreach ( $aAdded as $aItem ) {
				$oItem = $oWpPosts->getById( $aItem[ 'uniq' ] );
				$aReport[ 'lines' ] = sprintf( 'Post Published (slug:%s): "%s"', $oItem->post_name, $oItem->post_title );
			}
		}
		return $aReport;
	}

	/**
	 * @param array $aChanged
	 * @return array
	 */
	protected function processChanged( $aChanged ) {
		$aReport = [];
		if ( !empty( $aChanged ) ) {
			$aReport[ 'title' ] = "Posts Changed";
			$aReport[ 'lines' ] = [];
			$oWpPosts = Services::WpPost();
			foreach ( $aChanged as $sUniqId => $aAttributes ) {
				$oItem = $oWpPosts->getById( $sUniqId );
				$aReport[ 'lines' ] = sprintf( 'Post "%s" (slug:%s) changed attributes: %s',
					$oItem->post_title, $oItem->post_name, implode( ', ', $aAttributes ) );
			}
		}
		return $aReport;
	}

	/**
	 * @param array $aRemoved
	 * @return array
	 */
	protected function processRemoved( $aRemoved ) {
		$aReport = [];
		if ( !empty( $aRemoved ) ) {
			$aReport[ 'title' ] = 'Posts Removed';
			$aReport[ 'lines' ] = [];
			foreach ( $aRemoved as $aItem ) {
				$aReport[ 'lines' ] = sprintf( 'Post Un-Published (slug:%s): "%s"', $aItem[ 'slug' ], $aItem[ 'title' ] );
			}
		}
		return $aReport;
	}
}