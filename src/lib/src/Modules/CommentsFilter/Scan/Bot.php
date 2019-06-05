<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Bot {

	use ModConsumer;

	/**
	 * @param int $nPostId
	 * @return true|\WP_Error
	 */
	public function scan( $nPostId ) {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $oMod */
		$oMod = $this->getMod();

		$oReq = Services::Request();
		$sFieldCheckboxName = $oReq->post( 'cb_nombre' );
		$sFieldHoney = $oReq->post( 'sugar_sweet_email' );
		$nCooldownTs = (int)$oReq->post( 'botts' );
		$sCommentToken = $oReq->post( 'comment_token' );

		$nCooldown = $oMod->getTokenCooldown();
		$nExpire = $oMod->getTokenExpireInterval();

		$sKey = null;
		$sExplanation = null;
		if ( !$sFieldCheckboxName || !$oReq->post( $sFieldCheckboxName ) ) {
			$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'checkbox', 'wp-simple-firewall' ) );
			$sKey = 'checkbox';
		}
		// honeypot check
		else if ( !empty( $sFieldHoney ) ) {
			$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'honeypot', 'wp-simple-firewall' ) );
			$sKey = 'honeypot';
		}
		else if ( $nCooldown > 0 || $nExpire > 0 ) {

			if ( $nCooldown > 0 && $oReq->ts() < $nCooldownTs ) {
				$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'cooldown', 'wp-simple-firewall' ) );
				$sKey = 'cooldown';
			}
			else if ( $nExpire > 0 && $oReq->ts() > ( $nCooldownTs - $nCooldown + $nExpire ) ) {
				$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'expired', 'wp-simple-firewall' ) );
				$sKey = 'expired';
			}
			else if ( !$this->checkTokenHash( $sCommentToken, $nCooldownTs, $nPostId ) ) {
				$sExplanation = sprintf( __( 'Failed GASP Bot Filter Test (%s)', 'wp-simple-firewall' ), __( 'comment token failure', 'wp-simple-firewall' ) );
				$sKey = 'token';
			}
		}

		$mResult = true;
		if ( !empty( $sKey ) ) {
			$mResult = new \WP_Error( $sKey, $sExplanation, [] );
		}

		return $mResult;
	}

	/**
	 * @param $sToken
	 * @param $nTs
	 * @param $nPostId
	 * @return bool
	 */
	private function checkTokenHash( $sToken, $nTs, $nPostId ) {
		$sStoredToken = Services::WpGeneral()
								->getTransient( $this->getMod()->prefix( sprintf( 'comtok-%s-%s', $nPostId, $nTs ) ) );
		return hash_equals(
			(string)$sStoredToken,
			$sToken
		);
	}
}
