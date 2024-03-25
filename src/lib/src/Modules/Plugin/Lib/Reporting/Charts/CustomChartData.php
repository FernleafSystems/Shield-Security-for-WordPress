<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

class CustomChartData extends BaseBuildChartData {

	protected function preProcessRequest() {
		parent::preProcessRequest();

		/** @var CustomChartRequestVO $req */
		$req = $this->getChartRequest();

		switch ( \current( $req->events ) ) {
			case 'comment_block':
				$req->events = \array_filter(
					\array_keys( self::con()->comps->events->getEvents() ),
					function ( $event ) {
						return \strpos( $event, 'spam_block_' ) === 0;
					}
				);
				break;
			case 'bot_blocks':
				$req->events = \array_filter(
					\array_keys( self::con()->comps->events->getEvents() ),
					function ( $event ) {
						return \strpos( $event, 'bottrack_' ) === 0;
					}
				);
				break;
			default:
				break;
		}
	}
}