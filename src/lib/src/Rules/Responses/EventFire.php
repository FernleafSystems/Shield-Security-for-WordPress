<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class EventFire extends Base {

	public const SLUG = 'event_fire';

	public function execResponse() :void {
		$params = $this->params;
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
	}

	public function getParamsDef() :array {
		return [
			'event'            => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Event To Trigger', 'wp-simple-firewall' ),
			],
			'audit_params_map' => [
				'type'    => EnumParameters::TYPE_ARRAY,
				'default' => [],
				'label'   => __( 'Activity Log Parameter Map', 'wp-simple-firewall' ),
			],
		];
	}
}