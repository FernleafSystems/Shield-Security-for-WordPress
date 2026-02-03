<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Hasher;

class SnapComments extends BaseSnap {

	/**
	 * @return array[]
	 */
	public function snap() :array {
		$actual = [];

		$params = [
			'type'   => 'comment',
			'status' => 'all,spam,trash',
			'number' => 100,
			'paged'  => 1,
		];
		do {
			/** @var \WP_Comment[] $query */
			$query = get_comments( $params );
			if ( \is_array( $query ) ) {
				foreach ( $query as $comment ) {
					$actual[ $comment->comment_ID ] = [
						'uniq'         => $comment->comment_ID,
						'post_id'      => $comment->comment_post_ID,
						'status'       => $comment->comment_approved,
						'hash_content' => Hasher::Item( $comment->comment_content ),
					];
				}
			}
			$params[ 'paged' ]++;
		} while ( !empty( $query ) );

		return $actual;
	}

	/**
	 * @param \WP_Comment $item
	 */
	public function updateItemOnSnapshot( array $snapshotData, $item ) :array {
		if ( $item instanceof \WP_Comment ) {
			$snapshotData[ $item->comment_ID ] = [
				'uniq'         => $item->comment_ID,
				'post_id'      => $item->comment_post_ID,
				'status'       => $item->comment_approved,
				'hash_content' => Hasher::Item( $item->comment_content ),
			];
		}
		return $snapshotData;
	}

	/**
	 * @param \WP_Comment $item
	 */
	public function deleteItemOnSnapshot( array $snapshotData, $item ) :array {
		if ( $item instanceof \WP_Comment ) {
			unset( $snapshotData[ $item->comment_ID ] );
		}
		return $snapshotData;
	}
}