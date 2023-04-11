<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;

use function FernleafSystems\Wordpress\Plugin\Shield\Functions\get_plugin;

class ActivityLogMessageBuilder {

	public static function BuildFromLogRecord( LogRecord $log, string $logSeparator = "\n" ) :array {
		return explode( "\n", self::Build( $log->event_slug, $log->meta_data ?? [], $logSeparator ) );
	}

	public static function Build( string $event, array $substitutions = [], string $logSeparator = "\n" ) :string {
		$srvEvents = get_plugin()->getController()->loadEventsService();

		$raw = implode( $logSeparator, $srvEvents->getEventAuditStrings( $event ) );

		$stringSubs = [];
		foreach ( $substitutions as $subKey => $subValue ) {
			$stringSubs[ sprintf( '{{%s}}', $subKey ) ] = $subValue;
		}

		$log = preg_replace( '#{{[a-z_]+}}#i', 'missing data', strtr( $raw, $stringSubs ) );

		$auditCount = (int)( $substitutions[ 'audit_count' ] ?? 1 );
		if ( $srvEvents->getEventDef( $event )[ 'audit_countable' ] && $auditCount > 1 ) {
			$log .= $logSeparator.sprintf( __( 'This event repeated %s times in the last 24hrs.', 'wp-simple-firewall' ), $auditCount );
		}

		return $log;
	}
}