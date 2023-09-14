<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\DB\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Services\Services;

class DebugRecentEvents extends Actions\Render\BaseRender {

	use Actions\Traits\SecurityAdminNotRequired;

	public const SLUG = 'render_debug_recentevents';
	public const TEMPLATE = '/wpadmin/components/recent_events.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'title_recent'        => __( 'Recent Events Log', 'wp-simple-firewall' ),
				'box_receve_subtitle' => sprintf( __( 'Some of the most recent %s events', 'wp-simple-firewall' ),
					self::con()->getHumanName() ),
			],
			'vars'    => [
				'insight_events' => $this->getData()
			],
		];
	}

	private function getData() :array {
		$srvEvents = self::con()->loadEventsService();

		$theStats = \array_filter(
			$srvEvents->getEvents(),
			function ( $evt ) {
				return !empty( $evt[ 'recent' ] );
			}
		);

		/** @var EventsDB\Select $selector */
		$selector = self::con()
						->getModule_Events()
						->getDbH_Events()
						->getQuerySelector();

		$recent = \array_intersect_key(
			\array_filter( \array_map(
				function ( $entry ) use ( $srvEvents ) {
					/** @var EventsDB\Record $entry */
					return $srvEvents->eventExists( $entry->event ) ?
						[
							'name' => $srvEvents->getEventName( $entry->event ),
							'val'  => Services::WpGeneral()->getTimeStringForDisplay( $entry->created_at )
						]
						: null;
				},
				$selector->getLatestForAllEvents()
			) ),
			$theStats
		);

		foreach ( \array_keys( $theStats ) as $eventKey ) {
			if ( !isset( $recent[ $eventKey ] ) ) {
				$recent[ $eventKey ] = [
					'name' => $srvEvents->getEventName( $eventKey ),
					'val'  => __( 'Not yet recorded', 'wp-simple-firewall' )
				];
			}
		}

		return $recent;
	}
}