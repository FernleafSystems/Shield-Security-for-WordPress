<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\Ops;

/**
 * @property int $ip_ref
 * @property int $notbot_at
 * @property int $altcha_at
 * @property int $frontpage_at
 * @property int $loginpage_at
 * @property int $bt404_at
 * @property int $btcheese_at
 * @property int $btfake_at
 * @property int $btinvalidscript_at
 * @property int $btauthorfishing_at
 * @property int $btloginfail_at
 * @property int $btlogininvalid_at
 * @property int $btua_at
 * @property int $btxml_at
 * @property int $cooldown_at
 * @property int $auth_at
 * @property int $offense_at
 * @property int $blocked_at
 * @property int $unblocked_at
 * @property int $bypass_at
 * @property int $humanspam_at
 * @property int $markspam_at
 * @property int $unmarkspam_at
 * @property int $captchapass_at
 * @property int $captchafail_at
 * @property int $ratelimit_at
 * @property int $updated_at
 * @property int $snsent_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public $modified = false;

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( $key === 'ip_ref' ) {
			$value = (int)$value;
		}

		return $value;
	}

	public function __set( string $key, $value ) {
		if ( \serialize( $value ) !== \serialize( $this->{$key} ) ) {
			$this->modified = true;
		}
		parent::__set( $key, $value );
	}
}