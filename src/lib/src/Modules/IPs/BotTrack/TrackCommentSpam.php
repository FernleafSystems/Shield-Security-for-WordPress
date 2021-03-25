<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;

class TrackCommentSpam {

	use Shield\Modules\ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return is_admin() || is_network_admin();
	}

	protected function run() {
		add_action( 'spammed_comment', function ( $id ) {
			$comment = get_comment( $id );
			if ( $comment instanceof \WP_Comment && !empty( $comment->comment_author_IP ) ) {
				/** @var Shield\Modules\IPs\ModCon $mod */
				$mod = $this->getMod();
				$mod->getBotSignalsController()
					->getEventListener()
					->fireEventForIP( $comment->comment_author_IP, 'comment_markspam' );
			}
		} );

		add_action( 'unspammed_comment', function ( $id ) {
			$comment = get_comment( $id );
			if ( $comment instanceof \WP_Comment && !empty( $comment->comment_author_IP ) ) {
				/** @var Shield\Modules\IPs\ModCon $mod */
				$mod = $this->getMod();
				$mod->getBotSignalsController()
					->getEventListener()
					->fireEventForIP( $comment->comment_author_IP, 'comment_unmarkspam' );
			}
		} );
	}
}