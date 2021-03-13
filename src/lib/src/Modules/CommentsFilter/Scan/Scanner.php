<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities;
use FernleafSystems\Wordpress\Services\Services;

class Scanner {

	use ModConsumer;
	use ExecOnce;

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
		add_filter( 'pre_comment_approved', [ $this, 'setStatus' ], 1 );
		add_filter( 'pre_comment_content', [ $this, 'insertStatusExplanation' ], 1, 1 );
	}

	/**
	 * @param mixed $mStatus
	 * @return int|string|null
	 */
	public function setStatus( $mStatus ) {
		if ( !is_null( $this->spamStatus ) && in_array( $this->spamStatus, [ '0', 'spam', 'trash' ] ) ) {
			$mStatus = $this->spamStatus;
		}
		return $mStatus;
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
				default:
				case '0':
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
	 * @param array $aCommData
	 * @return array
	 */
	public function checkComment( $aCommData ) {
		$opts = $this->getOptions();

		if ( Services::WpComments()->isCommentSubmission()
			 && $this->getIfDoCommentsCheck( $aCommData[ 'comment_post_ID' ], $aCommData[ 'comment_author_email' ] ) ) {

			$mResult = $this->runScans( $aCommData );
			if ( is_wp_error( $mResult ) ) {

				$this->getCon()
					 ->fireEvent(
						 'spam_block_'.$mResult->get_error_code(),
						 [ 'audit' => $mResult->get_error_data() ]
					 );

				if ( $mResult->get_error_code() == 'human' ) {
					$status = $opts->getOpt( 'comments_default_action_human_spam' );
				}
				else {
					$status = $opts->getOpt( 'comments_default_action_spam_bot' );
				}

				if ( $status == 'reject' ) {
					Services::Response()->redirectToHome();
				}

				$this->spamStatus = $status;
				$this->spamReason = $mResult->get_error_message();
			}
		}

		return $aCommData;
	}

	/**
	 * @param array $aCommData
	 * @return true|\WP_Error|null
	 */
	private function runScans( $aCommData ) {
		/** @var CommentsFilter\ModCon $mod */
		$mod = $this->getMod();
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();

		$mResult = true;

		if ( !is_wp_error( $mResult ) && $opts->isEnabledGaspCheck() ) {
			$mResult = ( new Bot() )
				->setMod( $this->getMod() )
				->scan( $aCommData[ 'comment_post_ID' ] );
		}

		if ( !is_wp_error( $mResult ) && $opts->isEnabledAntiBot() ) {
			try {
				( new AntiBot() )
					->setMod( $this->getMod() )
					->scan();
			}
			catch ( \Exception $e ) {
				$mResult = new \WP_Error( 'antibot', $e->getMessage() );
			}
		}

		if ( !is_wp_error( $mResult ) && $opts->isEnabledCaptcha() && $mod->getCaptchaCfg()->ready ) {
			try {
				if ( $mod->getCaptchaCfg()->provider === 'hcaptcha' ) {
					( new Utilities\HCaptcha\TestRequest() )
						->setMod( $this->getMod() )
						->test();
				}
				else {
					( new Utilities\ReCaptcha\TestRequest() )
						->setMod( $this->getMod() )
						->test();
				}
			}
			catch ( \Exception $e ) {
				$mResult = new \WP_Error( 'recaptcha', $e->getMessage(), [] );
			}
		}

		if ( !is_wp_error( $mResult ) && $opts->isEnabledHumanCheck() ) {
			$mResult = ( new Human() )
				->setMod( $this->getMod() )
				->scan( $aCommData );
		}

		return $mResult;
	}

	/**
	 * @param int    $nPostId
	 * @param string $sCommentEmail
	 * @return bool
	 */
	public function getIfDoCommentsCheck( $nPostId, $sCommentEmail ) {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();
		$post = Services::WpPost()->getById( $nPostId );
		return $post instanceof \WP_Post
			   && Services::WpComments()->isCommentsOpen( $post )
			   && !( new IsEmailTrusted() )->trusted( $sCommentEmail, $opts->getApprovedMinimum(), $opts->getTrustedRoles() );
	}
}
