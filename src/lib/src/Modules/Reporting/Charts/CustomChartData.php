<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts;

class CustomChartData extends BaseBuildChartData {

	/**
	 * @inheritDoc
	 */
	protected function preProcessRequest() {
		parent::preProcessRequest();

		/** @var CustomChartRequestVO $req */
		$req = $this->getChartRequest();

		if ( $req->render_location === static::LOCATION_SUMMARYCARD ) {
			$req->interval = 'daily';
		}

		$theEvent = current( $req->events );
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