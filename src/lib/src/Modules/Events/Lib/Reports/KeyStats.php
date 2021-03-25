<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class KeyStats extends BaseReporter {

	/**
	 * @inheritDoc
	 */
	public function build() {
		$aAlerts = [];

		/** @var Events\ModCon $mod */
		$mod = $this->getMod();
		/** @var DBEvents\Select $oSelEvts */
		$oSelEvts = $mod->getDbHandler_Events()->getQuerySelector();
		/** @var Events\Strings $oStrings */
		$oStrings = $mod->getStrings();

		$aEventKeys = [
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

		$oRep = $this->getReport();

		$aCounts = [];
		foreach ( $aEventKeys as $sEvent ) {
			try {
				$nCount = $oSelEvts
					->filterByEvent( $sEvent )
					->filterByBoundary( $oRep->interval_start_at, $oRep->interval_end_at )
					->count();
				if ( $nCount > 0 ) {
					$aCounts[ $sEvent ] = [
						'count' => $nCount,
						'name'  => $oStrings->getEventName( $sEvent ),
					];
				}
			}
			catch ( \Exception $e ) {
			}
		}

		if ( count( $aCounts ) > 0 ) {
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/events/info_keystats.twig',
				[
					'vars'    => [
						'counts' => $aCounts
					],
					'strings' => [
						'title' => __( 'Top Security Statistics', 'wp-simple-firewall' ),
					],
					'hrefs'   => [
					],
				]
			);
		}

		return $aAlerts;
	}
}