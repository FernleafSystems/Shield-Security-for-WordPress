<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\OverviewCards;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Changelog\Retrieve;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	private function buildInsightsVars() :array {
		$con = $this->getCon();

		/** @var Shield\Modules\Reporting\UI $uiReporting */
		$uiReporting = $con->getModule_Reporting()->getUIHandler();

		return [
			'content' => [
				'tab_updates'   => $this->renderTabUpdates(),
				'tab_freetrial' => $this->renderFreeTrial(),
				'summary_stats' => $uiReporting->renderSummaryStats()
			],
			'vars'    => [
				'overview_cards' => ( new OverviewCards() )
					->setMod( $this->getMod() )
					->buildForShuffle(),
			],
			'hrefs'   => [
				'shield_pro_url'           => 'https://shsec.io/shieldpro',
				'shield_pro_more_info_url' => 'https://shsec.io/shld1',
			],
			'flags'   => [
				'show_ads'              => false,
				'show_standard_options' => false,
				'show_alt_content'      => true,
				'is_pro'                => $con->isPremiumActive(),
			],
			'strings' => [
				'tab_security_glance' => __( 'Security At A Glance', 'wp-simple-firewall' ),
				'tab_freetrial'       => __( 'Free Trial', 'wp-simple-firewall' ),
				'tab_updates'         => __( 'Updates and Changes', 'wp-simple-firewall' ),
				'tab_summary_stats'   => __( 'Summary Stats', 'wp-simple-firewall' ),
				'click_clear_filter'  => __( 'Click To Filter By Security Area or Status', 'wp-simple-firewall' ),
				'discover'            => __( 'Discover where your site security is doing well or areas that can be improved', 'wp-simple-firewall' ),
				'clear_filter'        => __( 'Clear Filter', 'wp-simple-firewall' ),
				'click_to_toggle'     => __( 'click to toggle', 'wp-simple-firewall' ),
				'go_to_options'       => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
				'key'                 => __( 'Key' ),
				'key_positive'        => __( 'Positive Security', 'wp-simple-firewall' ),
				'key_warning'         => __( 'Potential Warning', 'wp-simple-firewall' ),
				'key_danger'          => __( 'Potential Danger', 'wp-simple-firewall' ),
				'key_information'     => __( 'Information', 'wp-simple-firewall' ),
			],
		];
	}

	public function renderPages() :string {
		$con = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_Insights $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$sNavSection = $req->query( 'inav', 'overview' );
		$subNavSection = $req->query( 'subnav' );

		$modPlugin = $con->getModule_Plugin();
		$oTourManager = $modPlugin->getTourManager();
		if ( !$oTourManager->isCompleted( 'insights_overview' ) && $modPlugin->getActivateLength() > 600 ) {
			$oTourManager->setCompleted( 'insights_overview' );
		}

		$bIsPro = $con->isPremiumActive();
		switch ( $sNavSection ) {

			case 'logs':
				/** @var Shield\Modules\AuditTrail\UI $auditUI */
				$auditUI = $con->getModule_AuditTrail()->getUIHandler();
				/** @var Shield\Modules\Traffic\UI $trafficUI */
				$trafficUI = $con->getModule_Traffic()->getUIHandler();
				$data = [
					'content' => [
						'table_audit'   => $auditUI->renderAuditTrailTable(),
						'table_traffic' => $trafficUI->renderTrafficTable(),
					],
					'strings' => [
						'tab_audit'   => __( 'Audit Trail', 'wp-simple-firewall' ),
						'tab_traffic' => __( 'Traffic', 'wp-simple-firewall' ),
					],
				];
				break;

			case 'dashboard':
				/** @var Shield\Modules\Plugin\UI $UI */
				$UI = $con->getModule_Plugin()->getUIHandler();
				$data = $UI->buildInsightsVars_Dashboard();
				break;

			case 'debug':
				/** @var Shield\Modules\Plugin\UI $UI */
				$UI = $con->getModule_Plugin()->getUIHandler();
				$data = $UI->buildInsightsVars_Debug();
				break;

			case 'ips':
				/** @var Shield\Modules\IPs\UI $UI */
				$UI = $con->getModule_IPs()->getUIHandler();
				$data = $UI->buildInsightsVars();
				break;

			case 'license':
				/** @var Shield\Modules\License\UI $UILicense */
				$UILicense = $con->getModule_License()->getUIHandler();
				$data = $UILicense->buildInsightsVars();
				break;

			case 'scans':
				/** @var Shield\Modules\HackGuard\UI $UIHackGuard */
				$UIHackGuard = $con->getModule_HackGuard()->getUIHandler();
				$data = $UIHackGuard->buildInsightsVars();
				break;

			case 'importexport':
				$data = $modPlugin->getImpExpController()->buildInsightsVars();
				break;

			case 'reports':
				/** @var Shield\Modules\Reporting\UI $UIReporting */
				$UIReporting = $con->getModule_Reporting()->getUIHandler();
				$data = $UIReporting->buildInsightsVars();
				break;

			case 'users':
				/** @var Shield\Modules\UserManagement\UI $UIUsers */
				$UIUsers = $con->getModule( 'user_management' )->getUIHandler();
				$data = $UIUsers->buildInsightsVars();
				break;

			case 'settings':
				$data = [
					'ajax' => [
						'mod_options'          => $con->getModule( $subNavSection )
													  ->getAjaxActionData( 'mod_options', true ),
						'mod_opts_form_render' => $con->getModule( $subNavSection )
													  ->getAjaxActionData( 'mod_opts_form_render', true ),
					],
				];
				break;

			case 'overview':
			case 'index':
				$data = $this->buildInsightsVars();
				break;
			default:
				throw new \Exception( 'Not available' );
				break;
		}

		$aTopNav = [
			'settings'     => __( 'Plugin Settings', 'wp-simple-firewall' ),
			'dashboard'    => __( 'Dashboard', 'wp-simple-firewall' ),
			'overview'     => __( 'Overview', 'wp-simple-firewall' ),
			'scans'        => __( 'Scans', 'wp-simple-firewall' ),
			'ips'          => __( 'IPs', 'wp-simple-firewall' ),
			'logs'         => __( 'Logs', 'wp-simple-firewall' ),
			'users'        => __( 'Users', 'wp-simple-firewall' ),
			'license'      => __( 'Pro', 'wp-simple-firewall' ),
			'importexport' => __( 'Import', 'wp-simple-firewall' ),
			'reports'      => __( 'Reports', 'wp-simple-firewall' ),
			'debug'        => __( 'Debug', 'wp-simple-firewall' ),
			//			'importexport' => sprintf( '%s/%s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
		];
		if ( $bIsPro ) {
			unset( $aTopNav[ 'license' ] );
			$aTopNav[ 'license' ] = __( 'Pro', 'wp-simple-firewall' );
		}

		$activeNav = [];
		array_walk( $aTopNav, function ( &$name, $key ) use ( $sNavSection ) {
			$name = [
				'href'    => add_query_arg( [ 'inav' => $key ], $this->getMod()->getUrl_AdminPage() ),
				'name'    => $name,
				'slug'    => $key,
				'active'  => $key === $sNavSection,
				'subnavs' => [],
				'icon'    => ''
			];
		} );

		$aSearchSelect = [];
		$aSettingsSubNav = [];
		$activeSubNav = null;
		foreach ( $mod->getModulesSummaryData() as $slug => $summary ) {
			if ( $summary[ 'show_mod_opts' ] ) {
				$aSettingsSubNav[ $slug ] = [
					'href'   => add_query_arg( [ 'subnav' => $slug ], $aTopNav[ 'settings' ][ 'href' ] ),
					'name'   => $summary[ 'name' ],
					'active' => $slug === $subNavSection,
					'slug'   => $slug,
				];

				if ( $aSettingsSubNav[ $slug ][ 'active' ] ) {
					$activeSubNav = $aSettingsSubNav[ $slug ];
				}
				$aSearchSelect[ $summary[ 'name' ] ] = $summary[ 'options' ];
			}
		}

		if ( empty( $aSettingsSubNav ) ) {
			unset( $aTopNav[ 'settings' ] );
		}
		else {
			$aTopNav[ 'settings' ][ 'subnavs' ] = $aSettingsSubNav;
			if ( !empty( $activeSubNav ) ) {
				$aTopNav[ 'settings' ][ 'name' ] = sprintf( '%s: %s',
					__( 'Settings', 'wp-simple-firewall' ), $activeSubNav[ 'name' ] );
			}
		}

		$theNav = [
			'settings' => $aTopNav[ 'settings' ],
		];
		if ( empty( $aTopNav[ 'dashboard' ][ 'active' ] ) ) {
			$theNav[ 'back_to_dashboard' ] = [
				'href'    => add_query_arg( [ 'inav' => 'dashboard' ], $this->getMod()->getUrl_AdminPage() ),
				'name'    => '&larr;'.__( 'Back To Dashboard', 'wp-simple-firewall' ),
				'slug'    => 'dashboard',
				'active'  => false,
				'subnavs' => [],
				'icon'    => ''
			];
		}

		$DP = Services::DataManipulation();
		$data = $DP->mergeArraysRecursive(
			$this->getBaseDisplayData(),
			[
				'classes' => [
					'page_container' => 'page-insights page-'.$sNavSection
				],
				'flags'   => [
					'is_dashboard'     => $sNavSection === 'dashboard',
					'show_promo'       => !$bIsPro && ( $sNavSection != 'settings' ),
					'show_guided_tour' => $modPlugin->getIfShowIntroVideo(),
					'tours'            => [
						'insights_overview' => $oTourManager->canShow( 'insights_overview' )
					],
					'is_advanced'      => $modPlugin->isShowAdvanced()
				],
				'hrefs'   => [
					'back_to_dash' => $mod->getUrl_SubInsightsPage( 'dashboard' ),
					'go_pro'       => 'https://shsec.io/shieldgoprofeature',
					'nav_home'     => $mod->getUrl_AdminPage(),
					'top_nav'      => $theNav,
					'img_banner'   => $con->getPluginUrl_Image( 'pluginlogo_banner-170x40.png' )
				],
				'strings' => $mod->getStrings()->getDisplayStrings(),
				'vars'    => [
					'changelog_id'  => $con->getPluginSpec()[ 'meta' ][ 'announcekit_changelog_id' ],
					'search_select' => $aSearchSelect
				],
			],
			$data
		);
		return $mod->renderTemplate(
			sprintf( '/wpadmin_pages/insights/%s/index.twig', $sNavSection ),
			$data,
			true
		);
	}

	private function renderFreeTrial() :string {
		$user = Services::WpUsers()->getCurrentWpUser();
		return $this->getMod()
					->renderTemplate(
						'/forms/drip_trial_signup.twig',
						[
							'vars'    => [
								// the keys here must match the changelog item types
								'activation_url' => Services::WpGeneral()->getHomeUrl(),
								'email'          => $user->user_email,
								'name'           => $user->user_firstname,
							],
							'strings' => [
							],
						],
						true
					);
	}

	private function renderTabUpdates() :string {
		try {
			$changelog = ( new Retrieve() )
				->setCon( $this->getCon() )
				->fromRepo();
		}
		catch ( \Exception $e ) {
			$changelog = ( new Retrieve() )
				->setCon( $this->getCon() )
				->fromFile();
		}

		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/overview/updates/index.twig',
						[
							'vars'      => [
								// the keys here must match the changelog item types
								'badge_types' => [
									'new'      => 'primary',
									'added'    => 'light',
									'improved' => 'info',
									'changed'  => 'warning',
									'fixed'    => 'danger',
								]
							],
							'strings'   => [
								// the keys here must match the changelog item types
								'version'      => __( 'Version', 'wp-simple-firewall' ),
								'release_date' => __( 'Release Date', 'wp-simple-firewall' ),
								'pro_only'     => __( 'Pro Only', 'wp-simple-firewall' ),
								'full_release' => __( 'Full Release Announcement', 'wp-simple-firewall' ),
							],
							'changelog' => $changelog
						],
						true
					);
	}
}