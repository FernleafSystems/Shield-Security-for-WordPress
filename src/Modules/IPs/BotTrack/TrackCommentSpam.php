<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\ProcessOffense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class TrackCommentSpam {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return is_admin() || is_network_admin();
	}

	protected function run() {
		/**
		 * TODO: We need to be able to trigger normal events on specific IP addresses, not just the IP address of the current request.
		 */
		add_action( 'spammed_comment', function ( $id ) {
			$comment = get_comment( $id );
			if ( $comment instanceof \WP_Comment && !empty( $comment->comment_author_IP ) ) {

				try {
					// self::con()->comps->events->fireEvent( 'comment_markspam' );
					( new ProcessOffense() )
						->setIp( $comment->comment_author_IP )
						->incrementOffenses( 1, false, false );
				}
				catch ( \Exception $e ) {
				}

				self::con()
					->comps
					->bot_signals
					->getEventListener()
					->fireEventForIP( $comment->comment_author_IP, 'comment_markspam' );
			}
		} );

		add_action( 'unspammed_comment', function ( $id ) {
			$comment = get_comment( $id );
			if ( $comment instanceof \WP_Comment && !empty( $comment->comment_author_IP ) ) {
				self::con()
					->comps
					->bot_signals
					->getEventListener()
					->fireEventForIP( $comment->comment_author_IP, 'comment_unmarkspam' );
			}
		} );
	}
}