<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Insights\AdminNotes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CheckCaptchaSettings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\RecentEvents;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function getInsightsOverviewCards() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'General Settings', 'wp-simple-firewall' ),
			'subtitle'     => sprintf( __( 'General %s Settings', 'wp-simple-firewall' ),
				$this->getCon()->getHumanName() ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModuleEnabled() ) {
			$cards[] = $this->getModDisabledCard();
		}
		else {
			$bHasSupportEmail = Services::Data()->validEmail( $opts->getOpt( 'block_send_email_address' ) );
			$cards[ 'reports' ] = [
				'name'    => __( 'Reporting Email', 'wp-simple-firewall' ),
				'state'   => $bHasSupportEmail ? 1 : -1,
				'summary' => $bHasSupportEmail ?
					sprintf( __( 'Email address for reports set to: %s', 'wp-simple-firewall' ), $mod->getPluginReportEmail() )
					: sprintf( __( 'No reporting address provided - defaulting to: %s', 'wp-simple-firewall' ), $mod->getPluginReportEmail() ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'block_send_email_address' ),
			];

			$cards[ 'editing' ] = [
				'name'    => __( 'Visitor IP Detection', 'wp-simple-firewall' ),
				'state'   => 0,
				'summary' => sprintf( __( 'Visitor IP address source is: %s', 'wp-simple-firewall' ),
					__( $opts->getSelectOptionValueText( 'visitor_address_source' ), 'wp-simple-firewall' ) ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'visitor_address_source' ),
			];

			$bRecap = $mod->getCaptchaCfg()->ready;
			$cards[ 'recap' ] = [
				'name'    => __( 'CAPTCHA', 'wp-simple-firewall' ),
				'state'   => $bRecap ? 1 : -1,
				'summary' => $bRecap ?
					__( 'CAPTCHA keys have been provided', 'wp-simple-firewall' )
					: __( "CAPTCHA keys haven't been provided", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_third_party_captcha' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'plugin' => $cardSection ];
	}

	public function buildInsightsVars_Debug() :array {
		return [
			'vars'    => [
				'debug_data' => ( new Collate() )
					->setMod( $this->getMod() )
					->run()
			],
			'content' => [
				'recent_events' => ( new RecentEvents() )
					->setMod( $this->getMod() )
					->build(),
				'admin_notes' => ( new AdminNotes() )
					->setMod( $this->getMod() )
					->build()
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
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $mod */
		$mod = $this->getMod();
		$opts = $this->getOptions();
		$aWarnings = [];

		switch ( $section ) {
			case 'section_third_party_captcha':
				if ( $mod->getCaptchaCfg()->ready ) {
					if ( $opts->getOpt( 'captcha_checked_at' ) < 0 ) {
						( new CheckCaptchaSettings() )
							->setMod( $mod )
							->checkAll();
					}
					if ( $opts->getOpt( 'captcha_checked_at' ) == 0 ) {
						$aWarnings[] = sprintf(
							__( "Your captcha key and secret haven't been verified.", 'wp-simple-firewall' ).' '
							.__( "Please double-check and make sure you haven't mixed them about, and then re-save.", 'wp-simple-firewall' )
						);
					}
				}
				break;
		}

		return $aWarnings;
	}
}