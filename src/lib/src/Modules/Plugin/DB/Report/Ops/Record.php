<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops;

/**
 * @property string $type
 * @property string $interval_length
 * @property int    $interval_end_at
 * @property int    $sent_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	/**
	 * @param mixed $value
	 */
	public function __set( string $key, $value ) {
		$dbh = $this->getDbHandler();
		if ( isset( $this->id ) && !empty( $dbh ) ) {
			$dbh->getQueryUpdater()->updateRecord( $this, [
				$key => $value
			] );
		}
		parent::__set( $key, $value );
	}
}