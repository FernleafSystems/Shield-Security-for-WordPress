<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities;
use FernleafSystems\Wordpress\Services\Services;

class Scanner {

	use ModConsumer;

	/**
	 * @var string|int|null
	 */
	private $mCommentStatus;

	/**
	 * @var string
	 */
	private $sCommentExplanation;

	public function run() {
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
		if ( !is_null( $this->mCommentStatus ) && in_array( $this->mCommentStatus, [ '0', 'spam', 'trash' ] ) ) {
			$mStatus = $this->mCommentStatus;
		}
		return $mStatus;
	}

	/**
	 * @param string $sContent
	 * @return string
	 */
	public function insertStatusExplanation( $sContent ) {

		if ( !is_null( $this->mCommentStatus ) && in_array( $this->mCommentStatus, [ '0', 'spam', 'trash' ] ) ) {
			switch ( $this->mCommentStatus ) {
				case 'spam':
					$sHumanStatus = 'SPAM';
					break;
				case 'trash':
					$sHumanStatus = __( 'Trash' );
					break;
				default:
				case '0':
					$sHumanStatus = __( 'Pending Moderation' );
					break;
			}

			$sContent =
				'[* '.sprintf( __( '%s plugin marked this comment as "%s".', 'wp-simple-firewall' )
							   .' '.__( 'Reason: %s', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName(),
					$sHumanStatus,
					$this->sCommentExplanation

				)." *]\n"
				.$sContent;
		}

		return $sContent;
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
					$sStatus = $opts->getOpt( 'comments_default_action_human_spam' );
				}
				else {
					$sStatus = $opts->getOpt( 'comments_default_action_spam_bot' );
				}

				if ( $sStatus == 'reject' ) {
					Services::Response()->redirectToHome();
				}

				$this->mCommentStatus = $sStatus;
				$this->sCommentExplanation = $mResult->get_error_message();
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
			catch ( \Exception $oE ) {
				$mResult = new \WP_Error( 'recaptcha', $oE->getMessage(), [] );
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
