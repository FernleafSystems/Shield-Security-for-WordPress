<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\MetaRecords;

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
	 * Set on the stand-in for a user who has no record: the record is stored when the first thing is written to it.
	 */
	private bool $storesOnFirstWrite = false;

	public function storeOnFirstWrite() :self {
		$this->storesOnFirstWrite = true;
		return $this;
	}

	/**
	 * @param mixed $value
	 */
	public function __set( string $key, $value ) {
		if ( $this->storesOnFirstWrite ) {
			$stored = ( new MetaRecords() )->loadMeta( (int)$this->user_id );
			// If it couldn't be stored, the next thing written tries again.
			if ( $stored instanceof self ) {
				$this->storesOnFirstWrite = false;
				$this->applyFromArray( $stored->getRawData() );
			}
		}

		$dbh = $this->getDbH();
		if ( isset( $this->id ) && !empty( $dbh ) ) {
			$dbh->getQueryUpdater()->updateRecord( $this, [
				$key => $value
			] );
		}
		parent::__set( $key, $value );
	}
}
