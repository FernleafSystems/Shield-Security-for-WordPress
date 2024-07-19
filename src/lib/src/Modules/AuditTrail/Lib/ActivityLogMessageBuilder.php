<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActivityLogMessageBuilder {

	use PluginControllerConsumer;

	public static function BuildFromLogRecord( LogRecord $log, string $logSeparator = "\n" ) :array {
		return \explode( "\n", self::Build( $log->event_slug, $log->meta_data ?? [], $logSeparator ) );
	}

	public static function Build( string $event, array $metaData = [], string $logSeparator = "\n" ) :string {
		$raw = \implode( $logSeparator, self::con()->comps->events->getEventAuditStrings( $event ) );

		$stringSubs = [];
		foreach ( $metaData as $subKey => $subValue ) {
			$stringSubs[ sprintf( '{{%s}}', $subKey ) ] = $subValue;
		}

		$log = \preg_replace( '#{{[a-z_]+}}#i', 'missing data', \strtr( $raw, $stringSubs ) );

		$auditCount = (int)( $metaData[ 'audit_count' ] ?? 1 );
		$eventDef = self::con()->comps->events->getEventDef( $event );
		if ( $eventDef[ 'audit_countable' ] && $auditCount > 1 ) {
			$log .= $logSeparator.sprintf( __( 'This event repeated %s times in the last 24hrs.', 'wp-simple-firewall' ), $auditCount );
		}

		if ( !empty( $metaData[ 'snapshot_discovery' ] ) ) {
			$log = sprintf( '[%s] ', __( 'Discovered', 'wp-simple-firewall' ) ).$log;
		}

		return $log;
	}
}