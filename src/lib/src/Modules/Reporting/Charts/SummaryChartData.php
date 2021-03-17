<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SummaryChartData extends BaseBuildChartData {

	use ModConsumer;

	/**
	 * @inheritDoc
	 */
	public function build() :array {
		$this->preProcessRequest();
		return $this->buildDataForEvents( $this->getChartRequest()->events );
	}

	/**
	 * @inheritDoc
	 */
	protected function preProcessRequest() {
		parent::preProcessRequest();

		/** @var SummaryChartRequestVO $req */
		$req = $this->getChartRequest();

		if ( count( $req->events ) > 1 ) {
			throw new \InvalidArgumentException( 'There should be only 1 event.' );
		}

		if ( $req->render_location === $req::LOCATION_STATCARD ) {
			$req->interval = 'daily';
		}

		$theEvent = current($req->events);
		$possibleEvents = array_keys( $this->getCon()->loadEventsService()->getEvents() );
		switch ( $theEvent ) {
			case 'comment_block':
				$req->events = array_filter(
					$possibleEvents,
					function ( $event ) {
						return strpos( $event, 'spam_block_' ) === 0;
					}
				);
				break;
			case 'bot_blocks':
				$req->events = array_filter(
					$possibleEvents,
					function ( $event ) {
						return strpos( $event, 'bottrack_' ) === 0;
					}
				);
				break;
			default:
				break;
		}
	}
}