<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Hasher;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseSnapPosts extends BaseSnap {

	public function snap() :array {
		return $this->retrieve();
	}

	protected function retrieve( array $params = [] ) :array {
		$actual = [];

		$params = Services::DataManipulation()->mergeArraysRecursive(
			$this->getBaseParameters(), $params
		);

		do {
			/** @var \WP_Post[] $result */
			$result = get_posts( $params );
			if ( \is_array( $result ) ) {
				foreach ( $result as $post ) {
					$actual[ $post->ID ] = [
						'uniq'         => $post->ID,
						'slug'         => $post->post_name,
						'title'        => $post->post_title,
						'modified_at'  => \strtotime( $post->post_modified_gmt ),
						'hash_content' => Hasher::Item( $post->post_content ),
					];
				}
			}

			$params[ 'paged' ]++;
		} while ( !empty( $result ) );

		\ksort( $result );
		return $actual;
	}

	protected function getBaseParameters() :array {
		return [
			'numberposts' => 50,
			'post_status' => [ 'publish', 'private', 'draft', 'trash' ],
			'paged'       => 1,
			'post_type'   => 'post',
		];
	}

	/**
	 * @param \WP_Post $item
	 */
	public function updateItemOnSnapshot( array $snapshotData, $item ) :array {
		if ( $item instanceof \WP_Post ) {
			$snapshotData[ $item->ID ] = [
				'uniq'         => $item->ID,
				'slug'         => $item->post_name,
				'title'        => $item->post_title,
				'modified_at'  => \strtotime( $item->post_modified_gmt ),
				'hash_content' => Hasher::Item( $item->post_content ),
			];
		}
		return $snapshotData;
	}

	/**
	 * @param \WP_Post $item
	 */
	public function deleteItemOnSnapshot( array $snapshotData, $item ) :array {
		if ( $item instanceof \WP_Post ) {
			unset( $snapshotData[ $item->ID ] );
		}
		return $snapshotData;
	}
}