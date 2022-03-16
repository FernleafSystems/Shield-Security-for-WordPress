<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops;

/**
 * @property string $req_id
 * @property int    $ip_ref
 * @property string $type
 * @property string $path
 * @property string $verb
 * @property int    $code
 * @property bool   $offense
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'offense':
				$value = (bool)$value;
				break;

			default:
				break;
		}

		return $value;
	}
}