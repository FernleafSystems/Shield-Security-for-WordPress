<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\ModCon;

class KeyStats extends Base {

	const SLUG = 'render_keystats';
	const TEMPLATE = '/components/reports/mod/events/info_keystats.twig';

	protected function getRenderData() :array {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
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

		$sums = [];
		$srvEvents = $this->getCon()->loadEventsService();
		foreach ( $eventKeys as $event ) {
			try {
				$eventSum = $selector
					->filterByBoundary( $this->action_data[ 'interval_start_at' ], $this->action_data[ 'interval_end_at' ] )
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

		return [
			'strings' => [
				'title' => __( 'Top Security Statistics', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'counts' => $sums
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'interval_start_at',
			'interval_end_at',
		];
	}
}