<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class UserMeta
 * @package FernleafSystems\Wordpress\Plugin\Shield\Users
 * @property string $email_secret
 * @property bool   $email_validated
 * @property string $backupcode_secret
 * @property string $backupcode_validated
 * @property string $ga_secret
 * @property bool   $ga_validated
 * @property string $u2f_secret
 * @property bool   $u2f_validated
 * @property array  $hash_loginmfa
 * @property string $pass_hash
 * @property int    $first_seen_at
 * @property int    $last_verified_at
 * @property int    $pass_started_at
 * @property int    $pass_reset_last_redirect_at
 * @property int    $pass_check_failed_at
 * @property string $yubi_secret
 * @property bool   $yubi_validated
 * @property int    $last_login_at
 * @property bool   $wc_social_login_valid
 * @property bool   $hard_suspended_at
 * @property array  $tours
 */
class ShieldUserMeta extends \FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta {

	/**
	 * @param string   $sAgent
	 * @param int|null $nMaxExpires - allows us to clean out old entries
	 */
	public function addMfaSkipAgent( $sAgent, $nMaxExpires = null ) {
		$aHashes = is_array( $this->hash_loginmfa ) ? $this->hash_loginmfa : [];
		$aHashes[ md5( $sAgent ) ] = Services::Request()->ts();
		if ( !empty( $nMaxExpires ) ) {
			$aHashes = array_filter( $aHashes,
				function ( $nTS ) use ( $nMaxExpires ) {
					return Services::Request()->ts() - $nTS < $nMaxExpires;
				}
			);
		}
		$this->hash_loginmfa = $aHashes;
	}

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