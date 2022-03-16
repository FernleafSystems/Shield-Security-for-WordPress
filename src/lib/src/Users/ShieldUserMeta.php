<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Record;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Users\UserMeta;

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
 * @property int      $pass_reset_last_redirect_at
 * @property int      $pass_check_failed_at
 * @property bool     $wc_social_login_valid
 * @property array    $tours
 * /*** VIRTUAL ***
 * @property int      $last_verified_at
 * /*** REMOVED ***
 * @property int      $first_seen_at
 * @property int      $last_login_at
 * @property int      $pass_started_at
 * @property bool     $hard_suspended_at
 */
class ShieldUserMeta extends UserMeta {

	/**
	 * @var Record
	 */
	public $record;

	public function setUserMetaRecord( Record $meta ) :self {
		$this->record = $meta;
		return $this;
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'last_verified_at':
				$value = max( [
					$this->record->last_login_at,
					$this->record->pass_started_at,
					$this->record->first_seen_at
				] );
				break;
			default:
				break;
		}

		if ( function_exists( 'str_ends_with' ) && str_ends_with( $key, '_at' ) ) {
			$value = (int)$value;
		}

		return $value;
	}

	public function getLastVerifiedAt() :int {
		return (int)$this->last_verified_at;
	}

	public function updatePasswordStartedAt( string $userPassHash ) :self {
		$newHash = substr( sha1( $userPassHash ), 6, 4 );
		if ( !isset( $this->pass_hash ) || ( $this->pass_hash != $newHash ) ) {
			$this->pass_hash = $newHash;
			$this->record->pass_started_at = Services::Request()->ts();
		}
		return $this;
	}
}