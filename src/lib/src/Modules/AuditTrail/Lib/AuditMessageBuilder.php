<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;

class AuditMessageBuilder {

	public static function BuildFromLogRecord( LogRecord $log ) :string {
		$srvEvents = shield_security_get_plugin()->getController()->loadEventsService();
		$eventDef = $srvEvents->getEventDef( $log->event_slug );

		$rawMsgParts = $srvEvents->getEventAuditStrings( $log->event_slug );
		$rawString = implode( "\n", $rawMsgParts );

		$metaSubstitutions = $log->meta_data;

		if ( !empty( $eventDef[ 'audit_params' ] ) ) {
			$substitutions = array_intersect_key( $log->meta_data, array_flip( $eventDef[ 'audit_params' ] ) );
		}
		else {
			$substitutions = [];
		}

		if ( strpos( $rawString, '%s' ) !== false ) {

			// Get the defined audit parameters and align them with what's in the DB and order them
			if ( !empty( $eventDef[ 'audit_params' ] ) ) {

				if ( !empty( $substitutions ) ) {
					$metaSubstitutions = array_merge(
						array_flip( $eventDef[ 'audit_params' ] ),
						$substitutions
					);
				}
			}

			// In-case we're working with an older audit message without as much data substitutions
			$missingCount = substr_count( $rawString, '%s' ) - count( $metaSubstitutions );

			if ( $missingCount > 0 ) {
				$metaSubstitutions = array_merge(
					$metaSubstitutions,
					array_fill( 0, $missingCount, '[data missing]' )
				);
			}

			$final = stripslashes( sanitize_textarea_field( vsprintf( $rawString, $metaSubstitutions ) ) );
		}
		elseif ( preg_match( '#{{[a-z]+}}#i', $rawString ) ) {
			$final = self::SubString( $rawString, $substitutions );
		}
		else {
			$final = $rawString;
		}
		return $final;
	}

	// TODO: apply this approach to all logs including text file logs
	private static function SubString( string $raw, array $subs ) {
		$stringSubs = [];
		foreach ( $subs as $subKey => $subValue ) {
			$stringSubs[ sprintf( '{{%s}}', $subKey ) ] = $subValue;
		}
		$final = strtr( $raw, $stringSubs );
		return preg_replace( '#{{[a-z]+}}#i', 'missing data', $final );
	}

	public static function Build( string $event, array $metaSubstitutions = [] ) :string {
		$con = shield_security_get_plugin()->getController();
		$eventDef = $con->loadEventsService()->getEventDef( $event );

		$rawMsgParts = $con->getModule( $eventDef[ 'context' ] )
						   ->getStrings()
						   ->getAuditMessage( $event );
		$rawString = implode( "\n", $rawMsgParts );

		// In-case we're working with an older audit message without as much data substitutions
		$missingCount = substr_count( $rawString, '%s' ) - count( $metaSubstitutions );

		if ( $missingCount > 0 ) {
			$metaSubstitutions = array_merge(
				$metaSubstitutions,
				array_fill( 0, $missingCount, '[data missing for older audit logs]' )
			);
		}
		return stripslashes( sanitize_textarea_field( vsprintf( $rawString, $metaSubstitutions ) ) );
	}
}