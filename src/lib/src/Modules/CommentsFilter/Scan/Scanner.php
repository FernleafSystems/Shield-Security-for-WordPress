<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Services\Services;

/**
 * There's a bit of timing issue with the order of the available hooks/filters.
 * The disallowed keywords check is performed AFTER Shield updates the content of the comment to say it's been flagged.
 * So we must allow that content edit to happen, but if we discover that WP has already flagged a comment for whatever
 * reason, we should honour it and then remove our edits afterwards.
 */
class Scanner extends ExecOnceModConsumer {

	/**
	 * @var string|int|null
	 */
	private $spamStatus;

	/**
	 * @var string
	 */
	private $spamReason;

	protected function canRun() :bool {
		return Services::Request()->isPost();
	}

	protected function run() {
		if ( Services::WpComments()->isCommentSubmission() ) {
			add_filter( 'preprocess_comment', [ $this, 'checkComment' ], 5 );
		}
		add_filter( 'pre_comment_content', [ $this, 'insertStatusExplanation' ], 1 );
		add_filter( 'pre_comment_approved', [ $this, 'setStatus' ], 1 );
	}

	/**
	 * @param mixed $status
	 * @return int|string|null
	 */
	public function setStatus( $status ) {

		if ( !is_null( $this->spamStatus ) ) {

			// WP has already rejected the comment.
			if ( in_array( $status, [ 'spam', 'trash' ] ) ) {

				// We will have already updated the comment text with our spam explanation,
				// so we need to update the comment content after it's been stored in the DB in order to remove it.
				add_action( 'comment_post', function ( $commentID ) {

					// Remove this filter as it's called within `wp_update_comment()`
					remove_filter( 'pre_comment_content', [ $this, 'insertStatusExplanation' ], 1 );
					wp_update_comment(
						[
							'comment_ID'      => $commentID,
							'comment_content' => preg_replace( '/## Comment SPAM Protection:.*\s##\s/m', '', get_comment( $commentID )->comment_content ),
						]
					);
				} );
			}
			elseif ( in_array( $this->spamStatus, [ '0', 'spam', 'trash' ] ) ) {
				$status = $this->spamStatus;
			}
		}

		return $status;
	}

	/**
	 * @param string $content
	 * @return string
	 */
	public function insertStatusExplanation( $content ) {

		if ( !is_null( $this->spamStatus ) && in_array( $this->spamStatus, [ '0', 'spam', 'trash' ] ) ) {

			switch ( $this->spamStatus ) {
				case 'spam':
					$humanStatus = 'SPAM';
					break;

				case 'trash':
					$humanStatus = __( 'Trash' );
					break;

				case '0':
				default:
					$humanStatus = __( 'Pending Moderation' );
					break;
			}

			$additional = (string)apply_filters(
				'shield/comment_spam_explanation',
				sprintf(
					"## Comment SPAM Protection: %s %s ##\n",
					sprintf( __( '%s marked this comment as "%s".', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName(), $humanStatus ),
					sprintf( __( 'Reason: %s', 'wp-simple-firewall' ), $this->spamReason )
				),
				$this->spamStatus,
				$this->spamReason
			);
			$content = $additional.$content;
		}

		return $content;
	}

	/**
	 * @param array $commData
	 * @return array
	 */
	public function checkComment( $commData ) {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();

		if ( Services::WpComments()->isCommentSubmission()
			 && is_array( $commData )
			 && $this->getIfDoCommentsCheck( $commData[ 'comment_post_ID' ], $commData[ 'comment_author_email' ] ) ) {

			$spamErrors = $this->runScans( $commData );

			$errorCodes = $spamErrors->get_error_codes();
			if ( count( $errorCodes ) > 0 ) {

				foreach ( $errorCodes as $errorCode ) {
					$this->getCon()
						 ->fireEvent(
							 'spam_block_'.$errorCode,
							 [ 'audit_params' => $spamErrors->get_error_data( $errorCode ) ]
						 );
				}

				$this->getCon()->fireEvent( 'comment_spam_block' );

				// if we're configured to actually block...
				if ( $opts->isEnabledAntiBot() && in_array( 'antibot', $errorCodes ) ) {
					$status = $opts->getOpt( 'comments_default_action_spam_bot' );
				}
				elseif ( $opts->isEnabledHumanCheck() && in_array( 'human', $errorCodes ) ) {
					$status = $opts->getOpt( 'comments_default_action_human_spam' );
				}
				else {
					$status = null;
				}

				if ( !is_null( $status ) ) {
					if ( $status == 'reject' ) {
						Services::Response()->redirectToHome();
					}

					$this->spamStatus = $status;
					$this->spamReason = $spamErrors->get_error_message();
				}
			}
		}

		return $commData;
	}

	private function runScans( array $commData ) :\WP_Error {
		$errors = new \WP_Error();

		$isBot = $this->getCon()
					  ->getModule_IPs()
					  ->getBotSignalsController()
					  ->isBot();
		if ( $isBot ) {
			$errors->add( 'antibot', __( 'Failed AntiBot Verification', 'wp-simple-firewall' ) );
		}

		$humanError = ( new Human() )
			->setMod( $this->getMod() )
			->scan( $commData );
		if ( is_wp_error( $humanError ) ) {
			$code = $humanError->get_error_code();
			$errors->add( $code, $humanError->get_error_message( $code ), $humanError->get_error_data( $code ) );
		}

		return $errors;
	}

	/**
	 * @param int    $postID
	 * @param string $commentEmail
	 */
	public function getIfDoCommentsCheck( $postID, $commentEmail ) :bool {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();
		$post = Services::WpPost()->getById( $postID );
		return $post instanceof \WP_Post
			   && Services::WpComments()->isCommentsOpen( $post )
			   && !( new IsEmailTrusted() )->trusted( $commentEmail, $opts->getApprovedMinimum(), $opts->getTrustedRoles() );
	}
}
