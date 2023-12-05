<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class EventFire extends Base {

	public const SLUG = 'event_fire';

	public function execResponse() :bool {
		$params = $this->responseParams;
		$event = $params[ 'event' ] ?? '';
		if ( !empty( $event ) ) {
			unset( $params[ 'event' ] );

			// Translate rules condition meta items to audit trail params.
			if ( !empty( $params[ 'audit_params_map' ] ) ) {
				if ( empty( $params[ 'audit_params' ] ) ) {
					$params[ 'audit_params' ] = [];
				}
				$conditionMeta = $this->getConsolidatedConditionMeta();
				foreach ( $params[ 'audit_params_map' ] as $paramKey => $metaKey ) {
					if ( isset( $conditionMeta[ $metaKey ] ) ) {
						$params[ 'audit_params' ][ $paramKey ] = $conditionMeta[ $metaKey ];
					}
					else {
//						error_log( sprintf( 'firing event "%s" but missing condition meta key: %s', $event, $metaKey ) );
					}
				}
			}

//			error_log( var_export( $conditionMeta, true ) );
//			error_log( var_export( $params, true ) );
			self::con()->fireEvent( $event, $params );
		}

		return true;
	}
}