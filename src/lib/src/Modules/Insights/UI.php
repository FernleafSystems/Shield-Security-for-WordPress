<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Insights\AdminNotes;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Changelog\Retrieve;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function buildInsightsVars_Docs() :array {
		$con = $this->getCon();
		return [
			'content' => [
				'tab_updates' => $this->renderTabUpdates(),
				'tab_events'  => $this->renderTabEvents(),
			],
			'flags'   => [
				'is_pro' => $con->isPremiumActive(),
			],
			'hrefs'   => [
				'free_trial' => 'https://shsec.io/shieldfreetrialinplugin',
			],
			'strings' => [
				'tab_updates'   => __( 'Updates and Changes', 'wp-simple-firewall' ),
				'tab_events'    => __( 'Event Details', 'wp-simple-firewall' ),
				'tab_freetrial' => __( 'Free Trial', 'wp-simple-firewall' ),
			],
		];
	}

	private function buildInsightsVars_Overview() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		return [
			'content' => [
				'progress_meters' => ( new Lib\MeterAnalysis\Handler() )
					->setMod( $this->getMod() )
					->renderDashboardMeters(),
			],
			'strings' => [
				'click_clear_filter' => __( 'Click To Filter By Security Area or Status', 'wp-simple-firewall' ),
				'clear_filter'       => __( 'Clear Filter', 'wp-simple-firewall' ),
				'go_to_options'      => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
			],
			'vars'    => [
//				'select_i_want' => [
//					[
//						'href' => $mod->getUrl_SubInsightsPage( 'ips' ),
//						'name' => __( 'investigate the IP addresses on the block and bypass lists', 'wp-simple-firewall' ),
//					],
//					[
//						'href' => $mod->getUrl_SubInsightsPage( 'audit' ),
//						'name' => __( 'review user activity logs', 'wp-simple-firewall' ),
//					],
//					[
//						'href' => $mod->getUrl_SubInsightsPage( 'traffic' ),
//						'name' => __( 'review traffic and requests to my site', 'wp-simple-firewall' ),
//					],
//				]
			],
		];
	}

	public function renderPages() :string {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$inav = $req->query( 'inav', 'overview' );
		$subNavSection = $req->query( 'subnav' );

		$modPlugin = $con->getModule_Plugin();

		$data = [];
		switch ( $inav ) {

			case 'audit':
			case 'audit_trail':
				$modAudit = $con->getModule_AuditTrail();
				/** @var Shield\Modules\AuditTrail\UI $auditUI */
				$auditUI = $modAudit->getUIHandler();
				$data = [
					'content' => [
						'table_audit' => $auditUI->renderAuditTrailTable(),
					],
				];
				break;

			case 'traffic':
				$modTraffic = $con->getModule_Traffic();
				/** @var Shield\Modules\Traffic\UI $trafficUI */
				$trafficUI = $modTraffic->getUIHandler();
				$data = [
					'content' => [
						'table_traffic' => $trafficUI->renderTrafficTable(),
					],
				];
				break;

			case 'debug':
				/** @var Shield\Modules\Plugin\UI $UI */
				$UI = $con->getModule_Plugin()->getUIHandler();
				$data = $UI->buildInsightsVars_Debug();
				break;

			case 'docs':
				$data = $this->buildInsightsVars_Docs();
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
				$data = [
					'content' => [
						'licensing' => $UILicense->renderLicensePage()
					],
				];
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

			case 'rules':
				$data = [
					'content' => [
						'rules_summary' => $con->rules->renderSummary()
					]
				];
				break;

			case 'scans_results':
				/** @var Shield\Modules\HackGuard\UI $UIHackGuard */
				$UIHackGuard = $con->getModule_HackGuard()->getUIHandler();
				$data = $UIHackGuard->buildInsightsVars_Results();
				break;

			case 'scans_run':
				/** @var Shield\Modules\HackGuard\UI $UIHackGuard */
				$UIHackGuard = $con->getModule_HackGuard()->getUIHandler();
				$data = $UIHackGuard->buildInsightsVars_Run();
				break;

			case 'settings':
				$data = $con->modules[ $subNavSection ]->getUIHandler()->getBaseDisplayData();
				break;

			case 'stats':
				/** @var Shield\Modules\Events\UI $UIEvents */
				$UIEvents = $con->getModule_Events()->getUIHandler();
				$data = $UIEvents->buildInsightsVars();
				break;

			case 'users':
				/** @var Shield\Modules\UserManagement\UI $UIUsers */
				$UIUsers = $con->modules[ 'user_management' ]->getUIHandler();
				$data = $UIUsers->buildInsightsVars();
				break;

			case 'dashboard':
			case 'overview':
			case 'index':
				$data = $this->buildInsightsVars_Overview();
				break;

			case 'merlin':
				$data = [
					'content' => [
						'page_main' => ( new Lib\Merlin\MerlinController() )
							->setMod( $mod )
							->render( empty( $subNavSection ) ? 'guided_setup_wizard' : $subNavSection )
					],
					'flags'   => [
						'show_sidebar_nav' => 0
					],
				];
				break;
			default:
				throw new \Exception( 'Not available' );
		}

		$availablePages = [
			'stats'         => [
				__( 'Reporting', 'wp-simple-firewall' ),
				__( 'Quick Stats', 'wp-simple-firewall' ),
			],
			'reports'       => [
				__( 'Reporting', 'wp-simple-firewall' ),
				__( 'Charts', 'wp-simple-firewall' ),
			],
			'settings'      => __( 'Plugin Settings', 'wp-simple-firewall' ),
			'dashboard'     => __( 'Dashboard', 'wp-simple-firewall' ),
			'overview'      => __( 'Security Overview', 'wp-simple-firewall' ),
			'scans_results' => [
				__( 'Scans', 'wp-simple-firewall' ),
				__( 'Scan Results', 'wp-simple-firewall' ),
			],
			'scans_run'     => [
				__( 'Scans', 'wp-simple-firewall' ),
				__( 'Run Scans', 'wp-simple-firewall' ),
			],
			'ips'           => [
				__( 'IPs', 'wp-simple-firewall' ),
				__( 'Management & Analysis', 'wp-simple-firewall' ),
			],
			'audit'         => __( 'Activity Log', 'wp-simple-firewall' ),
			'audit_trail'   => [
				__( 'Logs', 'wp-simple-firewall' ),
				__( 'View Activity Logs', 'wp-simple-firewall' ),
			],
			'traffic'       => [
				__( 'Logs', 'wp-simple-firewall' ),
				__( 'View Traffic Logs', 'wp-simple-firewall' ),
			],
			'users'         => [
				__( 'Users', 'wp-simple-firewall' ),
				__( 'Sessions', 'wp-simple-firewall' ),
			],
			'license'       => [
				__( 'Licensing', 'wp-simple-firewall' ),
				__( 'ShieldPRO', 'wp-simple-firewall' ),
			],
			'free_trial'    => [
				__( 'Licensing', 'wp-simple-firewall' ),
				__( 'Free Trial', 'wp-simple-firewall' ),
			],
			'importexport'  => [
				__( 'Tools', 'wp-simple-firewall' ),
				sprintf( '%s / %s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
			],
			'notes'         => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Admin Notes', 'wp-simple-firewall' ),
			],
			'debug'         => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Debug', 'wp-simple-firewall' ),
			],
			'docs'          => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Docs', 'wp-simple-firewall' ),
			],
			'rules'         => [
				__( 'Tools', 'wp-simple-firewall' ),
				__( 'Rules', 'wp-simple-firewall' ),
			],
			'merlin'        => [
				__( 'Wizard', 'wp-simple-firewall' ),
				__( 'Guided Setup', 'wp-simple-firewall' ),
			],
		];

		$pageTitle = is_array( $availablePages[ $inav ] ) ? implode( ' > ', $availablePages[ $inav ] ) : $availablePages[ $inav ];
		if ( $inav === 'settings' && !empty( $subNavSection ) ) {
			$mod = $con->getModule( $subNavSection );
			$pageTitle = sprintf( '%s > %s',
				__( 'Configuration', 'wp-simple-firewall' ), empty( $mod ) ? 'Unknown Module' : $mod->getMainFeatureName() );
		}

		$data = Services::DataManipulation()->mergeArraysRecursive(
			$this->getBaseDisplayData(),
			[
				'classes' => [
					'page_container' => 'page-insights page-'.$inav
				],
				'flags'   => [
					'is_advanced' => $modPlugin->isShowAdvanced()
				],
				'hrefs'   => [
					'go_pro'   => 'https://shsec.io/shieldgoprofeature',
					'nav_home' => $mod->getUrl_AdminPage(),
				],
				'imgs'    => [
					'logo_banner' => $con->labels->url_img_pagebanner,
				],
				'strings' => [
					'page_title' => $pageTitle
				],
				'vars'    => [
					'active_module_settings' => $subNavSection,
					'navbar_menu'            => ( new Lib\NavMenuBuilder() )
						->setMod( $this->getMod() )
						->build()
				],
			],
			$data
		);

		$templateDir = in_array( $inav, [ 'merlin' ] ) ? 'default' : $inav;
		if ( strpos( $inav, 'scans_' ) === 0 ) {
			$templateDir = implode( '/', explode( '_', $inav, 2 ) );
		}

		return $mod->renderTemplate(
			sprintf( '/wpadmin_pages/insights/%s/index.twig', $templateDir ),
			$data
		);
	}

	private function renderTabEvents() :string {
		$con = $this->getCon();
		$srvEvents = $con->loadEventsService();

		$eventsSortedByLevel = [
			'Alert'   => [],
			'Warning' => [],
			'Notice'  => [],
			'Info'    => [],
			'Debug'   => [],
		];
		foreach ( $srvEvents->getEvents() as $event ) {
			$level = ucfirst( strtolower( $event[ 'level' ] ) );
			$eventsSortedByLevel[ $level ][ $event[ 'key' ] ] = [
				'name' => $srvEvents->getEventName( $event[ 'key' ] ),
				'attr' => [
					'stat'    => sprintf( 'Stat: %s', empty( $event[ 'stat' ] ) ? 'No' : 'Yes' ),
					'offense' => sprintf( 'Offense: %s', empty( $event[ 'offense' ] ) ? 'No' : 'Yes' ),
					'module'  => sprintf( 'Module: %s', $con->getModule( $event[ 'module' ] )->getMainFeatureName() ),
				]
			];
		}
		foreach ( $eventsSortedByLevel as &$events ) {
			ksort( $events );
		}

		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/docs/events.twig', [
			'vars'    => [
				// the keys here must match the changelog item types
				'event_defs' => $eventsSortedByLevel
			],
			'strings' => [
				// the keys here must match the changelog item types
				'version'      => __( 'Version', 'wp-simple-firewall' ),
				'release_date' => __( 'Release Date', 'wp-simple-firewall' ),
				'pro_only'     => __( 'Pro Only', 'wp-simple-firewall' ),
				'full_release' => __( 'Full Release Announcement', 'wp-simple-firewall' ),
			],
		] );
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

		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/overview/updates/index.twig', [
			'changelog' => $changelog,
			'strings'   => [
				// the keys here must match the changelog item types
				'version'      => __( 'Version', 'wp-simple-firewall' ),
				'release_date' => __( 'Release Date', 'wp-simple-firewall' ),
				'pro_only'     => __( 'Pro Only', 'wp-simple-firewall' ),
				'full_release' => __( 'Full Release Announcement', 'wp-simple-firewall' ),
			],
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
		] );
	}

	public function printAdminFooterItems() {
		$this->printGoProFooter();
		$this->printToastTemplate();
	}

	private function printGoProFooter() {
		$con = $this->getCon();
		$nav = Services::Request()->query( 'inav', 'overview' );
		echo $this->getMod()->renderTemplate( 'snippets/go_pro_banner.twig', [
			'flags' => [
				'show_promo' => $con->isModulePage()
								&& !$con->isPremiumActive()
								&& ( !in_array( $nav, [ 'scans_results', 'scans_run' ] ) ),
			],
			'hrefs' => [
				'go_pro' => 'https://shsec.io/shieldgoprofeature',
			]
		] );
	}

	private function printToastTemplate() {
		if ( $this->getCon()->isModulePage() ) {
			echo $this->getMod()->renderTemplate( 'snippets/toaster.twig', [
				'strings'     => [
					'title' => $this->getCon()->getHumanName(),
				],
				'js_snippets' => []
			] );
		}
	}
}