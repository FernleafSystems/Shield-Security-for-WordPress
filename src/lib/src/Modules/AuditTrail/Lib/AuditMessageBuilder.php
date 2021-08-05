<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

class AuditMessageBuilder {

	public static function Build( string $event, array $metaSubstitutions ) :string {
		$con = shield_security_get_plugin()->getController();
		if ( empty( $module ) ) {
			$module = $con->getModule( $con->loadEventsService()->getEventDef( $event )[ 'context' ] );
		}

		$rawString = implode( "\n", $module->getStrings()->getAuditMessage( $event ) );

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