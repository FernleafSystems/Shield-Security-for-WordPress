<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\ModCon;

class InfoKeyStats extends BaseBuilder {

	public const PRIMARY_MOD = 'events';
	public const SLUG = 'info_keystats';
	public const TEMPLATE = '/components/reports/components/info_keystats.twig';

	protected function getRenderData() :array {
		$counts = array_filter( array_map(
			function ( string $event ) {
				$countData = null;

				/** @var ModCon $mod */
				$mod = $this->primary_mod;
				/** @var DBEvents\Select $selector */
				$selector = $mod->getDbHandler_Events()->getQuerySelector();
				$report = $this->getReport();
				try {
					$intervalDiff = $report->interval_end_at - $report->interval_start_at;
					$eventSumLatest = $selector
						->filterByBoundary( $report->interval_start_at, $report->interval_end_at )
						->sumEvent( $event );
					$eventSumPrevious = $selector
						->filterByBoundary( $report->interval_start_at - $intervalDiff, $report->interval_start_at )
						->sumEvent( $event );
					if ( $eventSumLatest > 0 || $eventSumPrevious > 0 ) {
						$diff = $eventSumLatest - $eventSumPrevious;
						$countData = [
							'count_latest'   => $eventSumLatest,
							'count_previous' => $eventSumPrevious,
							'count_diff'     => abs( $diff ),
							'diff_symbol'    => $diff > 0 ? '↗' : ( $diff < 0 ? '↘' : '➡' ),
							'name'           => $this->getCon()->loadEventsService()->getEventName( $event ),
						];
					}
				}
				catch ( \Exception $e ) {
				}

				return $countData;
			},
			$this->getEventsToStat()
		) );

		$countsInRows = array_chunk( $counts, 2 );

		return [
			'flags'   => [
				'render_required' => !empty( $counts ),
			],
			'strings' => [
				'title' => __( 'Top Security Statistics', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'counts' => $countsInRows
			],
		];
	}

	/**
	 * @todo configurable
	 */
	private function getEventsToStat() :array {
		return [
			'ip_offense',
			'ip_blocked',
			'conn_kill',
			'firewall_block',
			'bottrack_404',
			'bottrack_fakewebcrawler',
			'bottrack_linkcheese',
			'bottrack_loginfailed',
			'bottrack_logininvalid',
			'bottrack_xmlrpc',
			'bottrack_invalidscript',
			'spam_block_bot',
			'spam_block_recaptcha',
			'spam_block_human',
		];
	}
}