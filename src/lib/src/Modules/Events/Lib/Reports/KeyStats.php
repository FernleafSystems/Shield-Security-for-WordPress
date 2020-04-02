<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO;
use FernleafSystems\Wordpress\Services\Services;

class KeyStats extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildInfo( ReportVO $oRep ) {
		$aAlerts = [];

		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		/** @var DBEvents\Select $oSelEvts */
		$oSelEvts = $oMod->getDbHandler_Events()->getQuerySelector();
		/** @var Events\Strings $oStrings */
		$oStrings = $oMod->getStrings();

		$aEventKeys = [
			'ip_offense',
			'conn_kill',
			'firewall_block',
		];

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
			catch ( \Exception $oE ) {
			}
		}

		if ( count( $aCounts ) > 0 ) {
			$oWP = Services::WpGeneral();
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/events/info_keystats.twig',
				[
					'vars'    => [
						'counts' => $aCounts
					],
					'strings' => [
						'title'       => __( 'Key Security Statistics', 'wp-simple-firewall' ),
						'subtitle'    => __( 'The following are statistics for important events that have occurred on your site.', 'wp-simple-firewall' ),
						'dates_below' => __( 'The information provided is for the dates below.', 'wp-simple-firewall' ),
						'dates'       => sprintf( '%s - %s',
							$oWP->getTimeStringForDisplay( $oRep->interval_start_at ),
							$oWP->getTimeStringForDisplay( $oRep->interval_end_at )
						),
					],
					'hrefs'   => [
					],
				]
			);
		}

		return $aAlerts;
	}
}