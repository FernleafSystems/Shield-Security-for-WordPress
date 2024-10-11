<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\AntiBot\CoolDownHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * There's a bit of timing issue with the order of the available hooks/filters.
 * The disallowed keywords check is performed AFTER Shield updates the content of the comment to say it's been flagged.
 * So we must allow that content edit to happen, but if we discover that WP has already flagged a comment for whatever
 * reason, we should honour it and then remove our edits afterward.
 */
class Scanner {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var string|int|null
	 */
	private $spamStatus = null;

	/**
	 * @var string
	 */
	private $spamReason;

	/**
	 * @var array
	 */
	private $spamCodes;

	protected function canRun() :bool {
		return Services::WpComments()->isCommentSubmission();
	}

	protected function run() {
		add_filter( 'pre_comment_approved', [ $this, 'checkComment' ], 11, 2 );
	}

	/**
	 * @param mixed $approval
	 * @param array $comm
	 * @return array
	 */
	public function checkComment( $approval, $comm ) {
		$con = self::con();

		// Note: use strict \in_array() here because when approval is '0', always returns 'true'
		if ( !\in_array( $approval, [ 'spam', 'trash' ], true )
			 && \is_array( $comm )
			 && \is_numeric( $comm[ 'comment_post_ID' ] ?? null )
			 && \is_string( $comm[ 'comment_author_email' ] ?? null )
			 && $this->isDoCommentsCheck( (int)$comm[ 'comment_post_ID' ], $comm[ 'comment_author_email' ] ) ) {

			$spamErrors = $this->runScans( $comm );

			$errorCodes = $spamErrors->get_error_codes();
			if ( \count( $errorCodes ) > 0 ) {

				foreach ( $errorCodes as $errorCode ) {
					$con->comps->events->fireEvent(
						'spam_block_'.$errorCode,
						[ 'audit_params' => $spamErrors->get_error_data( $errorCode ) ]
					);
				}

				$con->comps->events->fireEvent( 'comment_spam_block' );

				// if we're configured to actually block...
				if ( $con->comps->opts_lookup->enabledAntiBotCommentSpam() && \in_array( 'antibot', $errorCodes ) ) {
					$newStatus = $con->opts->optGet( 'comments_default_action_spam_bot' );
				}
				elseif ( $con->comps->opts_lookup->enabledHumanCommentSpam()
						 && \count( \array_intersect( [ 'human', 'humanrepeated', 'cooldown' ], $errorCodes ) ) > 0 ) {
					$newStatus = $con->opts->optGet( 'comments_default_action_human_spam' );
				}
				else {
					$newStatus = null;
				}

				if ( !\is_null( $newStatus ) ) {
					if ( $newStatus == 'reject' ) {
						Services::Response()->redirectToHome();
					}

					$approval = $newStatus;
					$this->spamStatus = $newStatus;
					$this->spamReason = $spamErrors->get_error_message();
					$this->spamCodes = $spamErrors->get_error_codes();

					// We add an explanation to the comment to explain the status assigned to the comment by Shield.
					add_action( 'comment_post', [ $this, 'insertExplanation' ], 9 );
				}
			}
		}

		return $approval;
	}

	private function runScans( array $commData ) :\WP_Error {
		$con = self::con();
		$errors = new \WP_Error();

		if ( $con->comps->bot_signals->isBot() ) {
			$errors->add( 'antibot', __( 'Failed AntiBot Verification', 'wp-simple-firewall' ) );
		}
		elseif ( $con->comps->opts_lookup->enabledHumanCommentSpam() ) {

			if ( $con->comps->cool_down->isCooldownTriggered( CoolDownHandler::CONTEXT_COMMENTS ) ) {
				$errors->add( 'cooldown', __( 'Comments Cooldown Triggered', 'wp-simple-firewall' ) );
			}
			else {
				$humanDict = ( new HumanDictionary() )->scan( $commData );

				if ( is_wp_error( $humanDict ) ) {
					$code = $humanDict->get_error_code();
					$errors->add( $code, $humanDict->get_error_message( $code ), $humanDict->get_error_data( $code ) );
				}
				else {
					// If the comment passes the dictionary spam lookup, maybe they have earlier detected spam
					$humanRepeat = ( new HumanRepeat() )->scan( $commData );
					if ( is_wp_error( $humanRepeat ) ) {
						$code = $humanRepeat->get_error_code();
						$errors->add( $code, $humanRepeat->get_error_message( $code ) );
					}
				}
			}

			$con->opts->optSet( 'last_comment_request_at', Services::Request()->ts() );
		}

		return $errors;
	}

	/**
	 * @param int $commentID
	 */
	public function insertExplanation( $commentID ) {

		$comment = get_comment( $commentID );
		if ( $comment instanceof \WP_Comment ) {

			switch ( $this->spamStatus ) {
				case 'spam':
					$humanStatus = 'SPAM';
					break;

				case 'trash':
					$humanStatus = __( 'Trash' );
					break;

				case 'hold':
				case '0':
				default:
					$humanStatus = __( 'Pending Moderation' );
					break;
			}

			$additional = (string)apply_filters(
				'shield/comment_spam_explanation',
				sprintf(
					"## Comment SPAM Protection: %s %s ##\n",
					sprintf( __( '%s marked this comment as "%s".', 'wp-simple-firewall' ), self::con()->labels->Name, $humanStatus ),
					sprintf( __( 'Reason: %s', 'wp-simple-firewall' ), $this->spamReason )
				),
				$this->spamStatus,
				$this->spamReason
			);

			wp_update_comment( [
				'comment_ID'      => $commentID,
				'comment_content' => $additional.$comment->comment_content,
			] );

			if ( !empty( $this->spamCodes ) ) {
				foreach ( $this->spamCodes as $spamCode ) {
					add_comment_meta( $commentID, self::con()->prefix( 'spam_'.$spamCode ), '1' );
				}
			}
		}
	}

	private function isDoCommentsCheck( int $postID, string $commentEmail ) :bool {
		$post = Services::WpPost()->getById( $postID );
		return $post instanceof \WP_Post
			   && Services::WpComments()->isCommentsOpen( $post )
			   && !( new IsEmailTrusted() )->emailTrusted( $commentEmail );
	}
}