<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;

class AuditMessageBuilder {

	public static function BuildFromLogRecord( LogRecord $log ) :string {
		return self::Build( $log->event_slug, $log->meta_data ?? [] );
	}

	public static function Build( string $event, array $substitutions = [] ) :string {
		$srvEvents = shield_security_get_plugin()->getController()->loadEventsService();

		$raw = implode( "\n", $srvEvents->getEventAuditStrings( $event ) );

		$stringSubs = [];
		foreach ( $substitutions as $subKey => $subValue ) {
			$stringSubs[ sprintf( '{{%s}}', $subKey ) ] = $subValue;
		}

		return preg_replace( '#{{[a-z]+}}#i', 'missing data', strtr( $raw, $stringSubs ) );
	}
}