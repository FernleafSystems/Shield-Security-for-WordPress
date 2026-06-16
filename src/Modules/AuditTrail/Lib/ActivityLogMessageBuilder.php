<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Text\SafeDisplayText;

class ActivityLogMessageBuilder {

	use PluginControllerConsumer;

	/**
	 * Compatibility wrapper for existing static consumers. New internal consumers should use
	 * BuildPlainLinesFromLogRecord() or BuildHtmlLinesFromLogRecord().
	 */
	public static function BuildFromLogRecord( LogRecord $log, string $logSeparator = "\n" ) :array {
		return \explode( "\n", self::BuildPlain( $log->event_slug, $log->meta_data ?? [], $logSeparator ) );
	}

	/**
	 * Compatibility wrapper for existing static consumers. New internal consumers should use BuildPlain().
	 */
	public static function Build( string $event, array $metaData = [], string $logSeparator = "\n" ) :string {
		return self::BuildPlain( $event, $metaData, $logSeparator );
	}

	public static function BuildPlainFromLogRecord( LogRecord $log, string $logSeparator = "\n" ) :string {
		return self::BuildPlain( $log->event_slug, $log->meta_data ?? [], $logSeparator );
	}

	/**
	 * @return list<string>
	 */
	public static function BuildPlainLinesFromLogRecord( LogRecord $log ) :array {
		return \explode( "\n", self::BuildPlainFromLogRecord( $log ) );
	}

	/**
	 * @return list<string>
	 */
	public static function BuildHtmlLinesFromLogRecord( LogRecord $log ) :array {
		return \array_map(
			static fn( string $line ) :string => esc_html( $line ),
			self::BuildPlainLinesFromLogRecord( $log )
		);
	}

	public static function BuildPlain( string $event, array $metaData = [], string $logSeparator = "\n" ) :string {
		$raw = \implode( $logSeparator, self::con()->comps->events->getEventAuditStrings( $event ) );

		$stringSubs = [];
		foreach ( $metaData as $subKey => $subValue ) {
			$stringSubs[ sprintf( '{{%s}}', $subKey ) ] = SafeDisplayText::inline( $subValue );
		}

		$log = \preg_replace( '#{{[a-z_]+}}#i', __( 'missing data', 'wp-simple-firewall' ), \strtr( $raw, $stringSubs ) );

		$auditCount = (int)( $metaData[ 'audit_count' ] ?? 1 );
		$eventDef = self::con()->comps->events->getEventDef( $event );
		if ( ( $eventDef[ 'audit_countable' ] ?? false ) && $auditCount > 1 ) {
			$log .= $logSeparator.sprintf( __( 'This event repeated %s times in the last 24hrs.', 'wp-simple-firewall' ), $auditCount );
		}

		if ( !empty( $metaData[ 'snapshot_discovery' ] ) ) {
			$log = sprintf( '[%s] ', __( 'Discovered', 'wp-simple-firewall' ) ).$log;
		}

		return $log;
	}
}
