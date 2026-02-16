<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Docs;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class EventsEnum extends Actions\Render\BaseRender {

	use Actions\Traits\SecurityAdminNotRequired;

	public const SLUG = 'render_docs_events';
	public const TEMPLATE = '/wpadmin/components/events_enum.twig';

	protected function getRenderData() :array {
		$srvEvents = self::con()->comps->events;

		$eventsSortedByLevel = [
			'Warning' => [],
			'Notice' => [],
			'Info'   => [],
		];
		foreach ( $srvEvents->getEvents() as $event ) {
			$level = \ucfirst( \strtolower( $event[ 'level' ] ) );
			if ( !isset( $eventsSortedByLevel[ $level ] ) ) {
				$level = 'Notice';
			}
			$eventsSortedByLevel[ $level ][ $event[ 'key' ] ] = [
				'name' => $srvEvents->getEventName( $event[ 'key' ] ),
				'attr' => [
					'stat'    => sprintf( __( 'Stat: %s', 'wp-simple-firewall' ), empty( $event[ 'stat' ] ) ? 'No' : 'Yes' ),
					'offense' => sprintf( __( 'Offense: %s', 'wp-simple-firewall' ), empty( $event[ 'offense' ] ) ? 'No' : 'Yes' ),
				]
			];
		}
		foreach ( $eventsSortedByLevel as &$events ) {
			\ksort( $events );
		}

		return [
			'vars' => [
				'event_defs' => $eventsSortedByLevel
			],
		];
	}
}
