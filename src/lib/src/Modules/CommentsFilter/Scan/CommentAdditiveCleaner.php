<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;

class CommentAdditiveCleaner {

	use ExecOnce;

	protected function run() {
		add_action( 'wp_set_comment_status', function ( $commentID, $newStatus ) {
			if ( \in_array( $newStatus, [ '0', 'hold', '1', 'approve' ], true ) ) {
				wp_update_comment(
					[
						'comment_ID'      => $commentID,
						'comment_content' => \preg_replace( '/## Comment SPAM Protection:.*\s##/m', '', get_comment( $commentID )->comment_content ),
					]
				);
			}
		}, 10, 2 );
	}
}