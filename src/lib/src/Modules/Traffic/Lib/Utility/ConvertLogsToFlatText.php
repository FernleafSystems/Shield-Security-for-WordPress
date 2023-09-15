<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ConvertLogsToFlatText {

	use PluginControllerConsumer;

	/**
	 * @param LogRecord[] $records
	 * @return array[]
	 */
	public function convert( array $records, bool $markUp = false ) :array {
		return \array_map(
			function ( LogRecord $record ) use ( $markUp ) {
				return $this->convertSingle( $record, $markUp );
			},
			$records
		);
	}

	public function convertSingle( LogRecord $rec, bool $markUpHtml = false ) :string {
		$path = $rec->path;
		if ( !empty( $rec->meta[ 'query' ] ) ) {
			$path .= '?'.( $markUpHtml ? esc_html( $rec->meta[ 'query' ] ) : $rec->meta[ 'query' ] );
		}
		return sprintf( "%s %s %s [%s] \"%s %s\" %s",
			( $markUpHtml && Services::IP()->isValidIp( $rec->ip ) ) ?
				sprintf( '<a href="%s" class="render_ip_analysis" data-ip="%s">%s</a>',
					self::con()->plugin_urls->ipAnalysis( $rec->ip ),
					$rec->ip,
					$rec->ip
				) : $rec->ip,
			'-',
			( $markUpHtml && !empty( $rec->uid ) ) ?
				sprintf( '<a href="%s" target="_blank">%s</a>',
					Services::WpUsers()->getAdminUrl_ProfileEdit( $rec->uid ),
					$rec->uid
				) : '-',
			Services::WpGeneral()->getTimeStampForDisplay( $rec->created_at ),
			$rec->verb,
			$path,
			$rec->code
		);
	}
}