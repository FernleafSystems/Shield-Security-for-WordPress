<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\OverviewCards;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Insights\AdminNotes;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Changelog\Retrieve;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function buildInsightsVars_Docs() :array {
		$con = $this->getCon();
		return [
			'content' => [
				'tab_updates'   => $this->renderTabUpdates(),
				'tab_freetrial' => $this->renderFreeTrial(),
			],
			'flags'   => [
				'is_pro' => $con->isPremiumActive(),
			],
			'strings' => [
				'tab_freetrial' => __( 'Free Trial', 'wp-simple-firewall' ),
				'tab_updates'   => __( 'Updates and Changes', 'wp-simple-firewall' ),
			],
		];
	}

	private function buildInsightsVars_Overview() :array {
		return [
			'vars'    => [
				'overview_cards' => ( new OverviewCards() )
					->setMod( $this->getMod() )
					->buildForShuffle(),
			],
			'strings' => [
				'click_clear_filter' => __( 'Click To Filter By Security Area or Status', 'wp-simple-firewall' ),
				'clear_filter'       => __( 'Clear Filter', 'wp-simple-firewall' ),
				'go_to_options'      => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
			],
		];
	}

	public function renderPages() :string {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$sNavSection = $req->query( 'inav', 'dashboard' );
		$subNavSection = $req->query( 'subnav' );

		$modPlugin = $con->getModule_Plugin();
		$oTourManager = $modPlugin->getTourManager();
		if ( !$oTourManager->isCompleted( 'insights_overview' ) && $modPlugin->getActivateLength() > 600 ) {
			$oTourManager->setCompleted( 'insights_overview' );
		}

		switch ( $sNavSection ) {

			case 'audit':
				/** @var Shield\Modules\AuditTrail\UI $auditUI */
				$auditUI = $con->getModule_AuditTrail()->getUIHandler();
				$data = [
					'content' => [
						'table_audit' => $auditUI->renderAuditTrailTable(),
					],
					'vars'    => [
						'related_hrefs' => [
							[
								'href'  => $con->getModule_AuditTrail()->getUrl_AdminPage(),
								'title' => __( 'Audit Trail Settings', 'wp-simple-firewall' ),
							],
							[
								'href'  => 'https://shsec.io/audittrailglossary',
								'title' => __( 'Audit Trail Glossary', 'wp-simple-firewall' ),
								'new'   => true,
							],
							[
								'href'  => $mod->getUrl_SubInsightsPage( 'traffic' ),
								'title' => __( 'Traffic Log', 'wp-simple-firewall' ),
							],
						]
					]
				];
				break;

			case 'traffic':
				/** @var Shield\Modules\Traffic\UI $trafficUI */
				$trafficUI = $con->getModule_Traffic()->getUIHandler();
				$data = [
					'content' => [
						'table_traffic' => $trafficUI->renderTrafficTable(),
					],
					'vars'    => [
						'related_hrefs' => [
							[
								'href'  => $con->getModule_Traffic()->getUrl_AdminPage(),
								'title' => __( 'Traffic Settings', 'wp-simple-firewall' ),
							],
							[
								'href'  => $mod->getUrl_SubInsightsPage( 'audit' ),
								'title' => __( 'Audit Trail', 'wp-simple-firewall' ),
							],
						]
					]
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

			case 'docs':
				$data = $this->buildInsightsVars_Docs();
				break;

			case 'free_trial':
				$data = [
					'content' => [
						'free_trial' => $this->renderFreeTrial()
					]
				];
				break;

			case 'importexport':
				$data = $modPlugin->getImpExpController()->buildInsightsVars();
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

			case 'notes':
				$data = [
					'content' => [
						'notes' => ( new AdminNotes() )
							->setMod( $modPlugin )
							->render()
					],
				];
				break;

			case 'reports':
				/** @var Shield\Modules\Reporting\UI $UIReporting */
				$UIReporting = $con->getModule_Reporting()->getUIHandler();
				$data = $UIReporting->buildInsightsVars();
				break;

			case 'scans':
				/** @var Shield\Modules\HackGuard\UI $UIHackGuard */
				$UIHackGuard = $con->getModule_HackGuard()->getUIHandler();
				$data = $UIHackGuard->buildInsightsVars();
				break;

			case 'settings':
				$data = $con->modules[ $subNavSection ]->getUIHandler()->getBaseDisplayData();
				break;

			case 'users':
				/** @var Shield\Modules\UserManagement\UI $UIUsers */
				$UIUsers = $con->modules[ 'user_management' ]->getUIHandler();
				$data = $UIUsers->buildInsightsVars();
				break;

			case 'overview':
			case 'index':
				$data = $this->buildInsightsVars_Overview();
				break;
			default:
				throw new \Exception( 'Not available' );
		}

		$availablePages = [
			'settings'     => __( 'Plugin Settings', 'wp-simple-firewall' ),
			'dashboard'    => __( 'Dashboard', 'wp-simple-firewall' ),
			'overview'     => __( 'Security Overview', 'wp-simple-firewall' ),
			'scans'        => __( 'Scans', 'wp-simple-firewall' ),
			'docs'         => __( 'Docs', 'wp-simple-firewall' ),
			'ips'          => __( 'IP Management and Analysis', 'wp-simple-firewall' ),
			'audit'        => __( 'Audit Trail', 'wp-simple-firewall' ),
			'traffic'      => __( 'Traffic', 'wp-simple-firewall' ),
			'notes'        => __( 'Admin Notes', 'wp-simple-firewall' ),
			'users'        => __( 'User Sessions', 'wp-simple-firewall' ),
			'license'      => __( 'ShieldPRO', 'wp-simple-firewall' ),
			'importexport' => __( 'Import / Export', 'wp-simple-firewall' ),
			'reports'      => __( 'Reports', 'wp-simple-firewall' ),
			'debug'        => __( 'Debug', 'wp-simple-firewall' ),
			'free_trial'   => __( 'Free Trial', 'wp-simple-firewall' ),
		];

		$modsToSearch = array_filter(
			$mod->getModulesSummaryData(),
			function ( $modSummary ) {
				return !empty( $modSummary[ 'show_mod_opts' ] );
			}
		);

		$pageTitle = $availablePages[ $sNavSection ];
		if ( !empty( $subNavSection ) ) {
			$pageTitle = sprintf( '%s: %s',
				__( 'Settings', 'wp-simple-firewall' ), $modsToSearch[ $subNavSection ][ 'name' ] );
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
					'show_guided_tour' => $modPlugin->getIfShowIntroVideo(),
					'tours'            => [
						'insights_overview' => false && $oTourManager->canShow( 'insights_overview' )
					],
					'is_advanced'      => $modPlugin->isShowAdvanced()
				],
				'hrefs'   => [
					'back_to_dash' => $mod->getUrl_SubInsightsPage( 'dashboard' ),
					'go_pro'       => 'https://shsec.io/shieldgoprofeature',
					'nav_home'     => $mod->getUrl_AdminPage(),
					'img_banner'   => $con->getPluginUrl_Image( 'pluginlogo_banner-170x40.png' )
				],
				'strings' => [
					'page_title' => $pageTitle
				],
				'vars'    => [
					'changelog_id'           => $con->getPluginSpec()[ 'meta' ][ 'announcekit_changelog_id' ],
					'mods'                   => $this->buildSelectData_ModuleSettings(),
					'search_select'          => $this->buildSelectData_OptionsSearch(),
					'active_module_settings' => $subNavSection
				],
			],
			$data
		);

		add_action( 'admin_footer', [ $this, 'printAdminFooterItems' ], 100, 0 );

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

	public function printAdminFooterItems() {
		$this->printGoProFooter();
		$this->printToastTemplate();
	}

	private function printGoProFooter() {
		$con = $this->getCon();
		$nav = Services::Request()->query( 'inav', 'dashboard' );
		echo $this->getMod()->renderTemplate(
			'snippets/go_pro_banner.twig',
			[
				'flags' => [
					'show_promo' => !$con->isPremiumActive()
									&& ( !in_array( $nav, [ 'scans' ] ) ),
				],
				'hrefs' => [
					'go_pro' => 'https://shsec.io/shieldgoprofeature',
				]
			]
		);
	}

	private function printToastTemplate() {
		if ( $this->getCon()->isModulePage() ) {
			echo $this->getMod()->renderTemplate(
				'snippets/toaster.twig',
				[
					'strings'     => [
						'title' => $this->getCon()->getHumanName(),
					],
					'js_snippets' => []
				]
			);
		}
	}

	private function printPluginDeactivateSurvey() {
		if ( Services::WpPost()->isCurrentPage( 'plugins.php' ) ) {

			$opts = [
				'reason_confusing'   => "It's too confusing",
				'reason_expected'    => "It's not what I expected",
				'reason_accident'    => "I downloaded it accidentally",
				'reason_alternative' => "I'm already using an alternative",
				'reason_trust'       => "I don't trust the developer :(",
				'reason_not_work'    => "It doesn't work",
				'reason_errors'      => "I'm getting errors",
			];

			echo $this->getMod()->renderTemplate( 'snippets/plugin-deactivate-survey.php', [
				'strings'     => [
					'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
				],
				'inputs'      => [
					'checkboxes' => Services::DataManipulation()->shuffleArray( $opts )
				],
				'js_snippets' => []
			] );
		}
	}
}