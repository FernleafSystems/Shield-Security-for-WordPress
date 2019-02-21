<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildComments
 * @package FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot
 */
class BuildComments {

	/**
	 * @return array[]
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
		$nPage = 0;
		do {
			$aParams[ 'offset' ] = $aParams[ 'number' ]*$nPage++;
			/** @var \WP_Comment[] $aQueryResult */
			$aQueryResult = get_comments( $aParams );
			if ( is_array( $aQueryResult ) ) {
				foreach ( $aQueryResult as $oComment ) {
					$aActual[ $oComment->comment_ID ] = [
						'uniq'         => $oComment->comment_ID,
						'post_id'      => $oComment->comment_post_ID,
						'modified_at'  => strtotime( $oComment->comment_date_gmt ),
						'hash_content' => sha1( $oComment->comment_content ),
						'is_approved'  => $oComment->comment_approved === '1',
						'is_spam'      => $oComment->comment_approved === 'spam',
						'is_trash'     => $oComment->comment_approved === 'trash',
					];
				}
			}

			$nPage++;
		} while ( !empty( $aQueryResult ) );

		return $aActual;
	}

	/**
	 * @return array
	 */
	protected function getBaseParameters() {
		return [
			'number' => 20,
			'status' => 'all,spam,trash'
		];
	}
}