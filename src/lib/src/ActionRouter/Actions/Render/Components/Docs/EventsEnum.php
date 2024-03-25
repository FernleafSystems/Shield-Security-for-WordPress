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
			'Alert'   => [],
			'Warning' => [],
			'Notice'  => [],
			'Info'    => [],
			'Debug'   => [],
		];
		foreach ( $srvEvents->getEvents() as $event ) {
			$level = \ucfirst( \strtolower( $event[ 'level' ] ) );
			$eventsSortedByLevel[ $level ][ $event[ 'key' ] ] = [
				'name' => $srvEvents->getEventName( $event[ 'key' ] ),
				'attr' => [
					'stat'    => sprintf( 'Stat: %s', empty( $event[ 'stat' ] ) ? 'No' : 'Yes' ),
					'offense' => sprintf( 'Offense: %s', empty( $event[ 'offense' ] ) ? 'No' : 'Yes' ),
				]
			];
		}
		foreach ( $eventsSortedByLevel as &$events ) {
			\ksort( $events );
		}

		return [
			'strings' => [
				// the keys here must match the changelog item types
				'version'      => __( 'Version', 'wp-simple-firewall' ),
				'release_date' => __( 'Release Date', 'wp-simple-firewall' ),
				'pro_only'     => __( 'Pro Only', 'wp-simple-firewall' ),
				'full_release' => __( 'Full Release Announcement', 'wp-simple-firewall' ),
			],
			'vars'    => [
				// the keys here must match the changelog item types
				'event_defs' => $eventsSortedByLevel
			],
		];
	}
}