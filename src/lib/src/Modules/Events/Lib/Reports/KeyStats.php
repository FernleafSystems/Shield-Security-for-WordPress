<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Services\Services;

class KeyStats extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildInfo() {
		$aAlerts = [];

		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
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
				$nCount = $this->getDbSelector()
							   ->filterByEvent( $sEvent )
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
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/events/info_keystats.twig',
				[
					'vars'    => [
						'counts' => $aCounts
					],
					'strings' => [
						'title'      => __( 'Key Security Statistics', 'wp-simple-firewall' ),
						'subtitle'   => __( 'Changes have been detected in the contents of critical files.', 'wp-simple-firewall' ),
						'date_range' => __( 'Changes have been detected in the contents of critical files.', 'wp-simple-firewall' ),
					],
					'hrefs'   => [
					],
				]
			);
		}

		return $aAlerts;
	}

	/**
	 * @return DBEvents\Select
	 * @throws \Exception
	 */
	protected function getDbSelector() {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		/** @var DBEvents\Select $oSelEvts */
		$oSelEvts = $oMod->getDbHandler_Events()->getQuerySelector();

		$oBoundary = Services::Request()->carbon();
		switch ( $this->getCon()->modules[ 'reporting' ]->getOptions()->getOpt( 'frequency_info' ) ) {
			case 'hourly':
				$oSelEvts->filterByBoundary_Hour( $oBoundary->subHours( 1 )->timestamp );
				break;
			case 'daily':
				$oSelEvts->filterByBoundary_Day( $oBoundary->subDays( 1 )->timestamp );
				break;
			case 'weekly':
				$oSelEvts->filterByBoundary_Week( $oBoundary->subWeeks( 1 )->timestamp );
				break;
			case 'biweekly':
				$oSelEvts->filterByBoundary(
					$oBoundary->subWeeks( 2 )->startOfWeek()->timestamp,
					$oBoundary->addWeeks( 1 )->endOfWeek()->timestamp
				);
				break;
			case 'monthly':
				$oSelEvts->filterByBoundary_Month( $oBoundary->subMonths( 1 )->timestamp );
				break;
			default:
				throw new \Exception( 'Not a supported frequency' );
				break;
		}
		return $oSelEvts;
	}
}