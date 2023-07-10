<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops\Record;

/**
 * @property string       $type
 * @property string       $interval
 * @property int          $interval_start_at
 * @property int          $interval_end_at
 * @property string       $content
 * @property Record|false $previous
 */
class ReportVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'content':
				$value = \trim( \is_string( $value ) ? $value : '' );
				break;

			case 'interval_start_at':
			case 'interval_end_at':
				$value = (int)$value;
				break;

			default:
				break;
		}
		return $value;
	}
}