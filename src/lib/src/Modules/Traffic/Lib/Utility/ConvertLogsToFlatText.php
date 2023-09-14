<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Services\Services;

class ConvertLogsToFlatText {

	/**
	 * @param LogRecord[] $records
	 * @return array[]
	 */
	public static function convert( array $records ) :array {
		$WP = Services::WpGeneral();
		return \array_map(
			function ( LogRecord $record ) use ( $WP ) {
				$path = $record->path;
				if ( !empty( $record->meta[ 'query' ] ) ) {
					$path .= '?'.$record->meta[ 'query' ];
				}
				return sprintf( "%s %s %s [%s] \"%s %s\" %s",
					$record->ip,
					'-',
					empty( $record->uid ) ? '-' : $record->uid,
					$WP->getTimeStampForDisplay( $record->created_at ),
					$record->verb,
					$path,
					$record->code
				);
			},
			$records
		);
	}
}