<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ConvertLogsToFlatText {

	use PluginControllerConsumer;

	/**
	 * @param LogRecord[] $records
	 * @return array[]
	 */
	public function convert( array $records, bool $markUpHtml = false ) :array {
		return \array_map(
			function ( LogRecord $record ) use ( $markUpHtml ) {
				return $this->convertSingle( $record, $markUpHtml );
			},
			$records
		);
	}

	public function convertSingle( LogRecord $rec, bool $markUpHtml = false ) :string {
		$path = $rec->path;
		if ( !empty( $rec->meta[ 'query' ] ) ) {
			$path .= '?'.$rec->meta[ 'query' ];
		}
		return sprintf( "%s %s %s [%s] \"%s %s\" %s",
			( $markUpHtml && Services::IP()->isValidIp( $rec->ip ) ) ?
				sprintf( '<a href="%s" class="offcanvas_ip_analysis" data-ip="%s">%s</a>',
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
			$markUpHtml ? esc_html( $path ) : $path,
			$rec->code
		);
	}
}