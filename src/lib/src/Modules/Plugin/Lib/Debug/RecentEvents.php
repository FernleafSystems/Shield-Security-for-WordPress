<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RecentEvents {

	use ModConsumer;

	public function build() :string {
		$con = $this->getCon();
		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/overview/recent_events.twig', [
			'strings' => [
				'title_recent'        => __( 'Recent Events Log', 'wp-simple-firewall' ),
				'box_receve_subtitle' => sprintf( __( 'Some of the most recent %s events', 'wp-simple-firewall' ), $con->getHumanName() ),
			],
			'vars'    => [
				'insight_events' => $this->getData()
			],
		] );
	}

	private function getData() :array {
		$srvEvents = $this->getCon()->loadEventsService();

		$theStats = array_filter(
			$srvEvents->getEvents(),
			function ( $evt ) {
				return !empty( $evt[ 'recent' ] );
			}
		);

		/** @var Events\Select $selector */
		$selector = $this->getCon()
						 ->getModule_Events()
						 ->getDbHandler_Events()
						 ->getQuerySelector();

		$recent = array_intersect_key(
			array_filter( array_map(
				function ( $entry ) use ( $srvEvents ) {
					/** @var Events\EntryVO $entry */
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

		$notYetRecorded = __( 'Not yet recorded', 'wp-simple-firewall' );
		foreach ( array_keys( $theStats ) as $eventKey ) {
			if ( !isset( $recent[ $eventKey ] ) ) {
				$recent[ $eventKey ] = [
					'name' => $srvEvents->getEventName( $eventKey ),
					'val'  => $notYetRecorded
				];
			}
		}

		return $recent;
	}
}