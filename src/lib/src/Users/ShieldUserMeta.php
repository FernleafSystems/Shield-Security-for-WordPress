<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Record;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property array    $login_intents
 * @property array    $email_secret
 * @property bool     $email_validated
 * @property string   $backupcode_secret
 * @property bool     $backupcode_validated
 * @property string   $ga_secret
 * @property bool     $ga_validated
 * @property array    $sms_registration
 * @property string   $u2f_secret
 * @property bool     $u2f_validated
 * @property string[] $u2f_regrequests
 * @property string   $yubi_secret
 * @property bool     $yubi_validated
 * @property array    $hash_loginmfa
 * @property string   $pass_hash
 * @property int      $first_seen_at
 * @property int      $pass_started_at
 * @property int      $pass_reset_last_redirect_at
 * @property int      $pass_check_failed_at
 * @property int      $last_login_at
 * @property bool     $wc_social_login_valid
 * @property bool     $hard_suspended_at
 * @property array    $tours
 */
class ShieldUserMeta extends \FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta {

	private $metaRecord;

	public function getUserMetaRecord() :Record {
		return $this->metaRecord;
	}

	public function setUserMetaRecord( Record $meta ) :self {
		$this->metaRecord = $meta;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getLastVerifiedAt() {
		return (int)max( [ $this->last_login_at, $this->pass_started_at, $this->first_seen_at ] );
	}

	/**
	 * @param string $hashedPassword
	 * @return $this
	 */
	public function setPasswordStartedAt( $hashedPassword ) {
		$newHash = substr( sha1( $hashedPassword ), 6, 4 );
		if ( !isset( $this->pass_hash ) || ( $this->pass_hash != $newHash ) ) {
			$this->pass_hash = $newHash;
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