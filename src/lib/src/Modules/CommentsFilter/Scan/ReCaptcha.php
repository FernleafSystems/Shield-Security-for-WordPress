<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Services\Services;

class ReCaptcha {

	/**
	 * @param array $aCommData
	 * @return bool
	 * @throws \Exception
	 */
	public function scan( $aCommData ) {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();

		try {
		}
		catch ( \Exception $oE ) {
			$sStatKey = ( $oE->getCode() == 1 ) ? 'empty' : 'failed';
			$sExplanation = $oE->getMessage();

			$this->doStatIncrement( sprintf( 'spam.recaptcha.%s', $sStatKey ) );
			self::$sCommentStatus = $this->getOption( 'comments_default_action_spam_bot' );
			$this->setCommentStatusExplanation( $sExplanation );

			if ( self::$sCommentStatus == 'reject' ) {
				Services::Response()->redirectToHome();
			}
		}

		return true;
	}
}
