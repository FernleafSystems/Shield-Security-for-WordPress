<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Hasher;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report\ZoneReportComments;

class SnapComments extends BaseZone {

	public const SLUG = 'comments';

	public function getZoneReporterClass() :string {
		return ZoneReportComments::class;
	}

	/**
	 * @return array[]
	 */
	public function snap() :array {
		$actual = [];

		$params = $this->getBaseParameters();
		$page = 0;
		do {
			$params[ 'offset' ] = $params[ 'number' ]*$page++;
			/** @var \WP_Comment[] $query */
			$query = get_comments( $params );
			if ( \is_array( $query ) ) {
				foreach ( $query as $comment ) {
					$actual[ $comment->comment_ID ] = [
						'uniq'         => $comment->comment_ID,
						'post_id'      => $comment->comment_post_ID,
						'modified_at'  => \strtotime( $comment->comment_date_gmt ),
						'hash_content' => Hasher::Item( $comment->comment_content ),
						'is_approved'  => $comment->comment_approved === '1',
						'is_spam'      => $comment->comment_approved === 'spam',
						'is_trash'     => $comment->comment_approved === 'trash',
					];
				}
			}

			$page++;
		} while ( !empty( $query ) );

		return $actual;
	}

	protected function getBaseParameters() :array {
		return [
			'number' => 100,
			'status' => 'all,spam,trash'
		];
	}
}