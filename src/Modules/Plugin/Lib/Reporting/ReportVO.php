<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops\Record;

/**
 * @property string       $type
 * @property string       $interval
 * @property int          $start_at
 * @property int          $end_at
 * @property array        $areas
 * @property array        $areas_data
 * @property string       $title
 * @property string       $content
 * @property Record|false $previous
 */
class ReportVO extends DynPropertiesClass {

	/**
	 * @var ?|Record
	 */
	public $record = null;

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'title':
			case 'content':
				$value = \trim( \is_string( $value ) ? $value : '' );
				break;
			case 'interval_start_at':
			case 'interval_end_at':
				$value = (int)$value;
				break;
			case 'areas_data':
			case 'areas':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}
		return $value;
	}
}