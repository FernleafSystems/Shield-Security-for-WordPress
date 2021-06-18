<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class UserMeta
 * @package FernleafSystems\Wordpress\Plugin\Shield\Users
 * @property array    $login_intents
 * @property array    $email_secret
 * @property bool     $email_validated
 * @property string   $backupcode_secret
 * @property string   $backupcode_validated
 * @property string   $ga_secret
 * @property bool     $ga_validated
 * @property string   $u2f_secret
 * @property bool     $u2f_validated
 * @property string[] $u2f_regrequests
 * @property array    $hash_loginmfa
 * @property string   $pass_hash
 * @property int      $first_seen_at
 * @property int      $last_verified_at
 * @property int      $pass_started_at
 * @property int      $pass_reset_last_redirect_at
 * @property int      $pass_check_failed_at
 * @property string   $yubi_secret
 * @property bool     $yubi_validated
 * @property int      $last_login_at
 * @property bool     $wc_social_login_valid
 * @property bool     $hard_suspended_at
 * @property array    $tours
 */
class ShieldUserMeta extends \FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta {

	/**
	 * @return int
	 */
	public function getLastVerifiedAt() {
		return (int)max( [ $this->last_login_at, $this->pass_started_at, $this->first_seen_at ] );
	}

	/**
	 * @param string $sHashedPassword
	 * @return $this
	 */
	public function setPasswordStartedAt( $sHashedPassword ) {
		$sNewHash = substr( sha1( $sHashedPassword ), 6, 4 );
		if ( !isset( $this->pass_hash ) || ( $this->pass_hash != $sNewHash ) ) {
			$this->pass_hash = $sNewHash;
			$this->pass_started_at = Services::Request()->ts();
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	public function updateFirstSeenAt() {
		if ( empty( $this->first_seen_at ) ) {
			$this->first_seen_at = max(
				0,
				min( array_filter( [
					Services::Request()->ts(),
					(int)$this->pass_started_at,
					(int)$this->last_login_at,
					(int)$this->pass_check_failed_at
				] ) )
			);
		}
		return $this;
	}
}