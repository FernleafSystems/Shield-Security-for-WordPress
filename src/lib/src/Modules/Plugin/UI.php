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

	public function buildInsightsVars_Wizard( $wizard, $step ) :array {
		$data = [];
		switch ( $wizard ) {
			case 'welcome':
				$data = [
					'steps'       => [
						'step1' => 'content for step1',
						'step2' => 'content for step2',
						'step3' => 'content for step3',
					],
					'currentStep' => 'step'.$step,
					'ajax'        => [
						'wizard_step' => $this->getMod()->getAjaxActionData( 'wizard_step', true ),
					],
					'strings'     => [
						'hohoho' => sprintf( __( '%s %s Page' ), $wizard, $this->getCon()->getHumanName() ),
					],
					'showSideNav' => 0,
				];
				break;
			default:
				break;
		}

		return $data;
	}

	/**
	 * @param array $option
	 * @return array
	 */
	protected function buildOptionForUi( $option ) {
		$option = parent::buildOptionForUi( $option );
		if ( $option[ 'key' ] === 'visitor_address_source' ) {
			$newOptions = [];
			$ipDetector = Services::IP()->getIpDetector();
			foreach ( $option[ 'value_options' ] as $valKey => $source ) {
				if ( $valKey == 'AUTO_DETECT_IP' ) {
					$newOptions[ $valKey ] = $source;
				}
				else {
					$IPs = implode( ', ', $ipDetector->getIpsFromSource( $source ) );
					if ( !empty( $IPs ) ) {
						$newOptions[ $valKey ] = sprintf( '%s (%s)', $source, $IPs );
					}
				}
			}
			$option[ 'value_options' ] = $newOptions;
		}
		return $option;
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
						$warnings[] = __( "Your captcha key and secret haven't been verified.", 'wp-simple-firewall' ).' '
									  .__( "Please double-check and make sure you haven't mixed them about, and then re-save.", 'wp-simple-firewall' );
					}
				}
				break;
		}

		return $warnings;
	}
}