<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\Ops;

/**
 * @property int $user_id
 * @property int $ip_ref
 * @property int $first_seen_at
 * @property int $last_login_at
 * @property int $last_2fa_verified_at
 * @property int $hard_suspended_at
 * @property int $pass_started_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	/**
	 * @param mixed $value
	 */
	public function __set( string $key, $value ) {
		$dbh = $this->getDbH();
		if ( isset( $this->id ) && !empty( $dbh ) ) {
			$dbh->getQueryUpdater()->updateRecord( $this, [
				$key => $value
			] );
		}
		parent::__set( $key, $value );
	}
}