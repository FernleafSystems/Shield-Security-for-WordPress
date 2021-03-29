<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CheckCaptchaSettings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\RecentEvents;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function buildInsightsVars_Dashboard() :array {
		return [
			'content' => [
				'dashboard_cards' => ( new Insights\DashboardCards() )
					->setMod( $this->getMod() )
					->renderAll(),
			],
		];
	}

	public function buildInsightsVars_Debug() :array {
		return [
			'strings' => [
				'page_title' => sprintf( __( '%s Debug Page' ), $this->getCon()->getHumanName() )
			],
			'vars'    => [
				'debug_data' => ( new Collate() )
					->setMod( $this->getMod() )
					->run()
			],
			'content' => [
				'recent_events' => ( new RecentEvents() )
					->setMod( $this->getMod() )
					->build(),
			]
		];
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 */
	protected function buildOptionForUi( $aOptParams ) {
		$aOptParams = parent::buildOptionForUi( $aOptParams );
		if ( $aOptParams[ 'key' ] === 'visitor_address_source' ) {
			$aNewOptions = [];
			$oIPDet = Services::IP()->getIpDetector();
			foreach ( $aOptParams[ 'value_options' ] as $sValKey => $sSource ) {
				if ( $sValKey == 'AUTO_DETECT_IP' ) {
					$aNewOptions[ $sValKey ] = $sSource;
				}
				else {
					$sIPs = implode( ', ', $oIPDet->getIpsFromSource( $sSource ) );
					$aNewOptions[ $sValKey ] = sprintf( '%s (%s)',
						$sSource, empty( $sIPs ) ? '-' : $sIPs );
				}
			}
			$aOptParams[ 'value_options' ] = $aNewOptions;
		}
		return $aOptParams;
	}

	protected function getSectionWarnings( string $section ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$opts = $this->getOptions();
		$warnings = [];

		switch ( $section ) {
			case 'section_third_party_captcha':
				if ( $mod->getCaptchaCfg()->ready ) {
					if ( $opts->getOpt( 'captcha_checked_at' ) < 0 ) {
						( new CheckCaptchaSettings() )
							->setMod( $mod )
							->checkAll();
					}
					if ( $opts->getOpt( 'captcha_checked_at' ) == 0 ) {
						$warnings[] = sprintf(
							__( "Your captcha key and secret haven't been verified.", 'wp-simple-firewall' ).' '
							.__( "Please double-check and make sure you haven't mixed them about, and then re-save.", 'wp-simple-firewall' )
						);
					}
				}
				break;
		}

		return $warnings;
	}
}