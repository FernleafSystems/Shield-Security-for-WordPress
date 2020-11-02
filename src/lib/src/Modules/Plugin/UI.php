<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Insights\AdminNotes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CheckCaptchaSettings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\RecentEvents;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function buildInsightsVars_Dashboard() :array {
		$con = $this->getCon();
		$modInsights = $con->getModule_Insights();
		$modPlugin = $con->getModule_Plugin();
		return [
			'vars' => [
				'feature_cards' => [

					'overview' => [
						'title'   => __( 'Security Overview', 'wp-simple-firewall' ),
						'paras'   => [
							sprintf( __( "Review your entire Shield Security configuration at a glance to see what's working and what isn't.", 'wp-simple-firewall' ), $con->getHumanName() ),
						],
						'actions' => [
							[
								'text' => __( "See My Security Overview", 'wp-simple-firewall' ),
								'href' => $modInsights->getUrl_SubInsightsPage( 'overview' ),
							],
						]
					],

					'scans' => [
						'title'   => __( 'Scans and Hack Protection', 'wp-simple-firewall' ),
						'paras'   => [
							sprintf( __( "Use %s Scans to automatically detect and repair intrusions on your site.", 'wp-simple-firewall' ), $con->getHumanName() ),
							sprintf( __( "%s scans WordPress core files, plugins, themes and will detect Malware (ShieldPRO).", 'wp-simple-firewall' ), $con->getHumanName() ),
						],
						'actions' => [
							[
								'text' => __( "Run Scans", 'wp-simple-firewall' ),
								'href' => $modInsights->getUrl_SubInsightsPage( 'scans' ),
							],
							[
								'text' => __( "Scans & Hack Guard Settings", 'wp-simple-firewall' ),
								'href' => $con->getModule_HackGuard()->getUrl_AdminPage(),
							],
						]
					],

					'sec_admin' => [
						'title'   => __( 'Security Admin', 'wp-simple-firewall' ),
						'paras'   => [
							sprintf( __( "Restrict access to %s and prevent unwanted changes to your site by other administrators.", 'wp-simple-firewall' ), $con->getHumanName() ),
						],
						'actions' => [
							[
								'text' => __( "Security Admin Settings", 'wp-simple-firewall' ),
								'href' => $con->getModule_SecAdmin()->getUrl_AdminPage(),
							],
						]
					],

					'ips' => [
						'title'   => __( 'IP Blocking and Bypass', 'wp-simple-firewall' ),
						'paras'   => [
							__( "Shield automatically detects and blocks bad IP addresses based on your security settings.", 'wp-simple-firewall' ),
							__( "The Bypass List ensures certain IPs are never blocked.", 'wp-simple-firewall' ),
							__( "The IP Analysis Tool will show you all information for a given IP as it relates to your site.", 'wp-simple-firewall' ),
						],
						'actions' => [
							[
								'text' => __( "Analyse & Manage IPs", 'wp-simple-firewall' ),
								'href' => $modInsights->getUrl_SubInsightsPage( 'ips' ),
							],
							[
								'text' => __( "IP Blocking Settings", 'wp-simple-firewall' ),
								'href' => $con->getModule_IPs()->getUrl_AdminPage(),
							],
						]
					],

					'audit_trail' => [
						'title'   => __( 'Audit Trail', 'wp-simple-firewall' ),
						'paras'   => [
							__( "Provides in-depth logging for all major WordPress events.", 'wp-simple-firewall' ),
						],
						'actions' => [
							[
								'text' => __( "View Audit Log", 'wp-simple-firewall' ),
								'href' => $modInsights->getUrl_SubInsightsPage( 'logs' ),
							],
							[
								'text' => __( "Audit Trail Settings", 'wp-simple-firewall' ),
								'href' => $con->getModule_AuditTrail()->getUrl_AdminPage(),
							],
						]
					],

					'users' => [
						'title'   => __( 'WordPress Users', 'wp-simple-firewall' ),
						'paras'   => [
							__( "Adds fine control over user sessions, account re-use, password strength and expiration, and user suspension.", 'wp-simple-firewall' ),
						],
						'actions' => [
							[
								'text' => __( "User Settings", 'wp-simple-firewall' ),
								'href' => $con->getModule_UserManagement()->getUrl_AdminPage(),
							],
							[
								'text' => __( "Manage User Sessions", 'wp-simple-firewall' ),
								'href' => $modInsights->getUrl_SubInsightsPage( 'users' ),
							],
						]
					],

					'traffic' => [
						'title'   => __( 'Traffic Logging', 'wp-simple-firewall' ),
						'paras'   => [
							__( "Use the traffic logging feature to monitor requests to your site as part of your visitor analysis.", 'wp-simple-firewall' ),
							__( "The Traffic Rate Limiting features lets you throttle how many requests any single visitor is allow to make to your site.", 'wp-simple-firewall' ),
						],
						'actions' => [
							[
								'text' => __( "View Traffic Log", 'wp-simple-firewall' ),
								'href' => $modInsights->getUrl_SubInsightsPage( 'logs' ),
							],
							[
								'text' => __( "Traffic Log Settings", 'wp-simple-firewall' ),
								'href' => $con->getModule_Traffic()->getUrl_AdminPage(),
							],
						]
					],

					'import' => [
						'title'   => __( 'Import/Export', 'wp-simple-firewall' ),
						'paras'   => [
							__( "Use the import/export feature to quickly setup a new site based on the settings of another site.", 'wp-simple-firewall' ),
							__( "You can also setup automatic syncing of settings between sites.", 'wp-simple-firewall' ),
						],
						'actions' => [
							[
								'text' => __( "Run Import/Export", 'wp-simple-firewall' ),
								'href' => $modInsights->getUrl_SubInsightsPage( 'importexport' ),
							],
							[
								'text' => __( "Import/Export Settings", 'wp-simple-firewall' ),
								'href' => $modPlugin->getUrl_DirectLinkToSection( 'section_importexport' ),
							],
						]
					],

					'debug' => [
						'title'   => __( 'Debug Info', 'wp-simple-firewall' ),
						'paras'   => [
							__( "If you contact support, they may ask you to show them your Debug Information page.", 'wp-simple-firewall' ),
							__( "It's also an interesting place to see a summary of your WordPress configuration in 1 place.", 'wp-simple-firewall' ),
						],
						'actions' => [
							[
								'text' => __( "View Debug Info", 'wp-simple-firewall' ),
								'href' => $modInsights->getUrl_SubInsightsPage( 'debug' ),
							],
						]
					],

				]
			]
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
				'admin_notes'   => ( new AdminNotes() )
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