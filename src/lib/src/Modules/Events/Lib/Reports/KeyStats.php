<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class KeyStats extends BaseReporter {

	public function build() :array {
		$alerts = [];

		/** @var Events\ModCon $mod */
		$mod = $this->getMod();
		/** @var DBEvents\Select $selector */
		$selector = $mod->getDbHandler_Events()->getQuerySelector();

		$eventKeys = [
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

		$rep = $this->getReport();

		$sums = [];
		$srvEvents = $this->getCon()->loadEventsService();
		foreach ( $eventKeys as $event ) {
			try {
				$eventSum = $selector
					->filterByBoundary( $rep->interval_start_at, $rep->interval_end_at )
					->sumEvent( $event );
				if ( $eventSum > 0 ) {
					$sums[ $event ] = [
						'count' => $eventSum,
						'name'  => $srvEvents->getEventName( $event ),
					];
				}
			}
			catch ( \Exception $e ) {
			}
		}

		if ( count( $sums ) > 0 ) {
			$alerts[] = $mod->renderTemplate( '/components/reports/mod/events/info_keystats.twig', [
				'strings' => [
					'title' => __( 'Top Security Statistics', 'wp-simple-firewall' ),
				],
				'hrefs'   => [
				],
				'vars'    => [
					'counts' => $sums
				],
			] );
		}

		return $alerts;
	}
}