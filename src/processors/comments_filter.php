<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_CommentsFilter extends Modules\BaseShield\ShieldProcessor {

	/**
	 */
	public function run() {
	}

	public function onWpInit() {
		parent::onWpInit();
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();
		$oWpUsers = Services::WpUsers();

		$bLoadComProc = !$oWpUsers->isUserLoggedIn() ||
						!( new CommentsFilter\Scan\IsEmailTrusted() )->trusted(
							$oWpUsers->getCurrentWpUser()->user_email,
							$oMod->getApprovedMinimum(),
							$oMod->getTrustedRoles()
						);

		if ( $bLoadComProc ) {

			if ( $oMod->isGoogleRecaptchaEnabled() ) {
				$this->getSubProRecaptcha()->execute();
			}

			if ( Services::Request()->isPost() ) {
				( new CommentsFilter\Scan\Scanner() )
					->setMod( $oMod )
					->run();
				add_filter( 'comment_notification_recipients', [ $this, 'clearCommentNotificationEmail' ], 100, 1 );
			}
			elseif ( $oMod->isEnabledGaspCheck() ) {
				$this->getSubProGasp()->execute();
			}
		}
	}

	public function runHourlyCron() {
		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();
		if ( $oMod->isEnabledGaspCheck() && function_exists( 'delete_expired_transients' ) ) {
			delete_expired_transients(); // cleanup unused comment tokens
		}
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'bot'       => 'ICWP_WPSF_Processor_CommentsFilter_BotSpam',
			'recaptcha' => 'ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha',
		];
	}

	/**
	 * @return \ICWP_WPSF_Processor_CommentsFilter_BotSpam
	 */
	private function getSubProGasp() {
		return $this->getSubPro( 'bot' );
	}

	/**
	 * @return \ICWP_WPSF_Processor_CommentsFilter_GoogleRecaptcha
	 */
	private function getSubProRecaptcha() {
		return $this->getSubPro( 'recaptcha' );
	}

	/**
	 * When you set a new comment as anything but 'spam' a notification email is sent to the post author.
	 * We suppress this for when we mark as trash by emptying the email notifications list.
	 * @param array $aEmails
	 * @return array
	 */
	public function clearCommentNotificationEmail( $aEmails ) {
		$sStatus = apply_filters( $this->getMod()->prefix( 'cf_status' ), '' );
		if ( in_array( $sStatus, [ 'reject', 'trash' ] ) ) {
			$aEmails = [];
		}
		return $aEmails;
	}
}