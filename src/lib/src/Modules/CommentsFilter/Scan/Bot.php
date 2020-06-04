<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Bot {

	use ModConsumer;

	/**
	 * @param int $nPostId
	 * @return true|\WP_Error
	 */
	public function scan( $nPostId ) {
		/** @var CommentsFilter\Options $oOpts */
		$oOpts = $this->getOptions();

		$oReq = Services::Request();
		$sFieldCheckboxName = $oReq->post( 'cb_nombre' );
		$sFieldHoney = $oReq->post( 'sugar_sweet_email' );
		$nCommentTs = (int)$oReq->post( 'botts' );
		$sCommentToken = $oReq->post( 'comment_token' );

		$nCooldown = $oOpts->getTokenCooldown();
		$nExpire = $oOpts->getTokenExpireInterval();

		$sKey = null;
		$sExplanation = null;
		if ( !$sFieldCheckboxName || !$oReq->post( $sFieldCheckboxName ) ) {
			$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'checkbox', 'wp-simple-firewall' ) );
			$sKey = 'checkbox';
		}
		// honeypot check
		elseif ( !empty( $sFieldHoney ) ) {
			$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'honeypot', 'wp-simple-firewall' ) );
			$sKey = 'honeypot';
		}
		elseif ( $nCooldown > 0 || $nExpire > 0 ) {

			if ( $nCooldown > 0 && $oReq->ts() < ( $nCommentTs + $nCooldown ) ) {
				$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'cooldown', 'wp-simple-firewall' ) );
				$sKey = 'cooldown';
			}
			elseif ( $nExpire > 0 && $oReq->ts() > ( $nCommentTs + $nExpire ) ) {
				$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'expired', 'wp-simple-firewall' ) );
				$sKey = 'expired';
			}
			elseif ( !$this->checkTokenHash( $sCommentToken, $nCommentTs, $nPostId ) ) {
				$sExplanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'token', 'wp-simple-firewall' ) );
				$sKey = 'token';
			}
		}

		return empty( $sKey ) ? true : new \WP_Error( 'bot', $sExplanation, [ 'type' => $sKey ] );
	}

	/**
	 * @param $sToken
	 * @param $nTs
	 * @param $nPostId
	 * @return bool
	 */
	private function checkTokenHash( $sToken, $nTs, $nPostId ) {
		$oWp = Services::WpGeneral();
		$sKey = $this->getMod()->prefix(
			'comtok-'.md5( sprintf( '%s-%s-%s', $nPostId, $nTs, Services::IP()->getRequestIp() ) )
		);
		$sStoredToken = Services::WpGeneral()->getTransient( $sKey );
		$oWp->deleteTransient( $sKey ); // single use hashes & clean as we go
		return hash_equals(
			(string)$sStoredToken,
			$sToken
		);
	}
}
