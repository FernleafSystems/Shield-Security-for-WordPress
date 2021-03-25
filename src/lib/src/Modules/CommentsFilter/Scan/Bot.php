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
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$sFieldCheckboxName = $req->post( 'cb_nombre' );
		$sFieldHoney = $req->post( 'sugar_sweet_email' );
		$nCommentTs = (int)$req->post( 'botts' );
		$sCommentToken = $req->post( 'comment_token' );

		$cooldown = $opts->getTokenCooldown();
		$expire = $opts->getTokenExpireInterval();

		$key = null;
		$explanation = null;
		if ( !$sFieldCheckboxName || !$req->post( $sFieldCheckboxName ) ) {
			$explanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'checkbox', 'wp-simple-firewall' ) );
			$key = 'checkbox';
		}
		// honeypot check
		elseif ( !empty( $sFieldHoney ) ) {
			$explanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'honeypot', 'wp-simple-firewall' ) );
			$key = 'honeypot';
		}
		elseif ( $cooldown > 0 || $expire > 0 ) {

			if ( $cooldown > 0 && $req->ts() < ( $nCommentTs + $cooldown ) ) {
				$explanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'cooldown', 'wp-simple-firewall' ) );
				$key = 'cooldown';
			}
			elseif ( $expire > 0 && $req->ts() > ( $nCommentTs + $expire ) ) {
				$explanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'expired', 'wp-simple-firewall' ) );
				$key = 'expired';
			}
			elseif ( !$this->checkTokenHash( $sCommentToken, $nCommentTs, $nPostId ) ) {
				$explanation = sprintf( __( 'Failed Bot Test (%s)', 'wp-simple-firewall' ), __( 'token', 'wp-simple-firewall' ) );
				$key = 'token';
			}
		}

		return empty( $key ) ? true : new \WP_Error( 'bot', $explanation, [ 'type' => $key ] );
	}

	/**
	 * @param $sToken
	 * @param $nTs
	 * @param $nPostId
	 * @return bool
	 */
	private function checkTokenHash( $sToken, $nTs, $nPostId ) {
		$WP = Services::WpGeneral();
		$key = $this->getCon()->prefix(
			'comtok-'.md5( sprintf( '%s-%s-%s', $nPostId, $nTs, Services::IP()->getRequestIp() ) )
		);
		$sStoredToken = Services::WpGeneral()->getTransient( $key );
		$WP->deleteTransient( $key ); // single use hashes & clean as we go
		return hash_equals(
			(string)$sStoredToken,
			$sToken
		);
	}
}
