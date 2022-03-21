<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

use FernleafSystems\Wordpress\Services\Services;

class BuildPosts {

	/**
	 * @return array[] - key is user ID, values are arrays with keys: id, user_login, user_pass, user_email, is_admin
	 */
	public function run() {
		return $this->retrieve();
	}

	/**
	 * @param array $aParams
	 * @return array[]
	 */
	protected function retrieve( $aParams = [] ) {
		$aActual = [];

		$aParams = Services::DataManipulation()->mergeArraysRecursive( $this->getBaseParameters(), $aParams );

		do {
			/** @var \WP_Post[] $aQueryResult */
			$aQueryResult = get_posts( $aParams );
			if ( is_array( $aQueryResult ) ) {
				foreach ( $aQueryResult as $oPost ) {
					$aActual[ $oPost->ID ] = [
						'uniq'         => $oPost->ID,
						'slug'         => $oPost->post_name,
						'title'        => $oPost->post_title,
						'modified_at'  => strtotime( $oPost->post_date_gmt ),
						'hash_content' => sha1( $oPost->post_content ),
						'hash_title'   => sha1( $oPost->post_title ),
					];
				}
			}

			$aParams[ 'paged' ]++;
		} while ( !empty( $aQueryResult ) );

		return $aActual;
	}

	/**
	 * @return array
	 */
	protected function getBaseParameters() {
		return [
			'numberposts' => 10,
			'post_status' => 'publish',
			'paged'       => 1,
			'post_type'   => 'post',
		];
	}
}