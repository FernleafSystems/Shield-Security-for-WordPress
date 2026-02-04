<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Events\FillEventAuditParamsFromRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class EventFire extends Base {

	public const SLUG = 'event_fire';

	public function execResponse() :void {
		$params = $this->p->getRawData();
		$event = $params[ 'event' ] ?? '';

		if ( !empty( $event ) ) {
			unset( $params[ 'event' ] );

			// Translate rules condition meta items to audit trail params.
			if ( !empty( $params[ 'audit_params_map' ] ) ) {
				if ( empty( $params[ 'audit_params' ] ) ) {
					$params[ 'audit_params' ] = [];
				}

				$conditionMeta = self::con()->rules->getConditionMeta();
				$params[ 'audit_params' ] = \array_merge( $params[ 'audit_params' ], $conditionMeta->getRawData() );
				foreach ( $params[ 'audit_params_map' ] as $paramKey => $metaKey ) {
					if ( isset( $params[ 'audit_params' ][ $metaKey ] ) ) {
						$params[ 'audit_params' ][ $paramKey ] = $params[ 'audit_params' ][ $metaKey ];
					}
				}
			}

			self::con()->fireEvent(
				$event,
				( new FillEventAuditParamsFromRequest() )->setThisRequest( $this->req )->run( $event, $params )
			);
		}
	}

	public function getParamsDef() :array {
		$events = self::con()->comps->events->getEventNames();
		return [
			'event'            => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $events ),
				'enum_labels' => $events,
				'label'       => __( 'Event To Trigger', 'wp-simple-firewall' ),
			],
			'offense_count'    => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 0,
				'label'   => __( 'Offense Count', 'wp-simple-firewall' ),
			],
			'block'            => [
				'type'    => EnumParameters::TYPE_BOOL,
				'default' => false,
				'label'   => __( 'Block IP Address?', 'wp-simple-firewall' ),
			],
			'audit_params_map' => [
				'type'    => EnumParameters::TYPE_ARRAY,
				'default' => [],
				'label'   => __( 'Activity Log Parameter Map', 'wp-simple-firewall' ),
			],
			'audit_params'     => [
				'type'    => EnumParameters::TYPE_ARRAY,
				'default' => [],
				'label'   => __( 'Activity Log Parameters', 'wp-simple-firewall' ),
			],
		];
	}
}