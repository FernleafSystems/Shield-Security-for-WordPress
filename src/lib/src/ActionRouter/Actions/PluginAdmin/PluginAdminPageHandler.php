<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions,
	Actions\DynamicPageLoad,
	Actions\MerlinAction,
	Actions\MfaBackupCodeAdd,
	Actions\MfaBackupCodeDelete
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\{
	NonceVerifyNotRequired,
	SecurityAdminNotRequired
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Services\Services;

class PluginAdminPageHandler extends Actions\BaseAction {

	use NonceVerifyNotRequired;
	use SecurityAdminNotRequired;

	public const SLUG = 'plugin_admin_page_handler';

	protected $pageHookSuffix;

	protected $screenID;

	protected function exec() {
		if ( $this->canRun() ) {

			if ( apply_filters( 'shield/show_admin_menu', $this->getCon()->cfg->menu[ 'show' ] ?? true ) ) {
				add_action( 'admin_menu', function () {
					$this->createAdminMenu();
				} );
				add_action( 'network_admin_menu', function () {
					$this->createNetworkAdminMenu();
				} );
			}

			add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
				return $this->customEnqueues( $enqueues );
			} );
			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				return $this->localiseScripts( $localz );
			} );
		}
	}

	private function canRun() :bool {
		return ( is_admin() || is_network_admin() ) && !Services::WpGeneral()->isAjax();
	}

	private function customEnqueues( array $enq ) :array {

		if ( $this->getCon()->isModulePage() ) {
			$nav = Services::Request()->query( Constants::NAV_ID );
			switch ( $nav ) {

				case PluginURLs::NAV_IMPORT_EXPORT:
					$enq[ Enqueue::JS ][] = 'shield/import';
					break;
				case PluginURLs::NAV_OVERVIEW:
					$enq[ Enqueue::JS ] = [
						'ip_detect'
					];
					break;
				case PluginURLs::NAV_REPORTS:
					$enq[ Enqueue::JS ] = [
						'chartist',
						'chartist-plugin-legend',
						'shield/charts',
					];
					$enq[ Enqueue::CSS ] = [
						'chartist',
						'chartist-plugin-legend',
						'shield/charts'
					];
					break;
				case PluginURLs::NAV_WIZARD:
					$enq[ Enqueue::JS ][] = 'shield/merlin';
					$enq[ Enqueue::CSS ][] = 'shield/merlin';
					break;

				default:
					$enq[ Enqueue::JS ][] = 'shield/tables';
					if ( in_array( $nav, [ PluginURLs::NAV_SCANS_RESULTS, PluginURLs::NAV_SCANS_RUN ] ) ) {
						$enq[ Enqueue::JS ][] = 'shield/scans';
					}
					break;
			}
		}

		$enq[ Enqueue::CSS ][] = 'wp-wp-jquery-ui-dialog';
		$enq[ Enqueue::JS ][] = 'wp-jquery-ui-dialog';

		return $enq;
	}

	private function localiseScripts( array $locals ) :array {
		$con = $this->getCon();

		$locals[] = [
			'shield/merlin',
			'merlin',
			[
				'ajax' => [
					'merlin_action' => ActionData::Build( MerlinAction::SLUG )
				],
				'vars' => [
					/** http://techlaboratory.net/jquery-smartwizard#advanced-options */
					'smartwizard_cfg' => [
						'selected'          => 0,
						'theme'             => 'dots',
						'justified'         => true,
						'autoAdjustHeight'  => true,
						'backButtonSupport' => true,
						'enableUrlHash'     => true,
						'lang'              => [
							'next'     => __( 'Next Step', 'wp-simple-firewall' ),
							'previous' => __( 'Previous Step', 'wp-simple-firewall' ),
						],
						'toolbar'           => [
							// both, top, none
							'position' => 'bottom',
							//							'extraHtml'     => '<a href="https://testing.aptotechnologies.com/test1/wp-admin/admin.php?page=icwp-wpsf-insights&amp;inav=overview"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-left" viewBox="0 0 16 16">
							//  <path fill-rule="evenodd" d="M6 12.5a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5v-9a.5.5 0 0 0-.5-.5h-8a.5.5 0 0 0-.5.5v2a.5.5 0 0 1-1 0v-2A1.5 1.5 0 0 1 6.5 2h8A1.5 1.5 0 0 1 16 3.5v9a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 5 12.5v-2a.5.5 0 0 1 1 0v2z"></path>
							//  <path fill-rule="evenodd" d="M.146 8.354a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L1.707 7.5H10.5a.5.5 0 0 1 0 1H1.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3z"></path>
							//</svg> Exit Wizard</a>',
						],
					]
				]
			]
		];

		$locals[] = [
			'shield/navigation',
			'shield_vars_navigation',
			[
				'ajax' => [
					'dynamic_load' => ActionData::Build( DynamicPageLoad::SLUG )
				]
			]
		];
		$locals[] = [
			'global-plugin',
			'icwp_wpsf_vars_lg',
			[
				'ajax' => [
					'profile_backup_codes_gen' => ActionData::Build( MfaBackupCodeAdd::SLUG ),
					'profile_backup_codes_del' => ActionData::Build( MfaBackupCodeDelete::SLUG ),
				],
			]
		];

		$tourManager = $con->getModule_Plugin()->getTourManager();
		$locals[] = [
			'shield/tours',
			'shield_vars_tourmanager',
			[
				'ajax'        => ActionData::Build( Actions\PluginMarkTourFinished::SLUG ),
				'tour_states' => $tourManager->getUserTourStates(),
				'tours'       => $tourManager->getAllTours(),
			]
		];

		$locals[] = [
			'plugin',
			'icwp_wpsf_vars_plugin',
			[
				'components' => [
					'helpscout'     => [
						'beacon_id' => $con->isPremiumActive() ? 'db2ff886-2329-4029-9452-44587df92c8c' : 'aded6929-af83-452d-993f-a60c03b46568',
						'visible'   => $con->isModulePage()
					],
					'ip_analysis'   => [
						'ajax' => [
							'ip_analyse_action' => ActionData::Build( Actions\IpAnalyseAction::SLUG ),
						]
					],
					'ip_rules'      => [
						'ajax'    => [
							'ip_rule_add_submit' => ActionData::Build( Actions\IpRuleAddSubmit::SLUG ),
							'ip_rule_delete'     => ActionData::Build( Actions\IpRuleDelete::SLUG ),
						],
						'strings' => [
							'are_you_sure' => __( 'Are you sure you want to delete this IP Rule?', 'wp-simple-firewall' ),
						],
					],
					'offcanvas'     => [
						'ip_analysis'      => Actions\Render\Components\OffCanvas\IpAnalysis::SLUG,
						'ip_rule_add_form' => Actions\Render\Components\OffCanvas\IpRuleAddForm::SLUG,
						'meter_analysis'   => Actions\Render\Components\OffCanvas\MeterAnalysis::SLUG,
						'mod_config'       => Actions\Render\Components\OffCanvas\ModConfig::SLUG,
					],
					'mod_options'   => [
						'ajax' => [
							'mod_options_save' => ActionData::Build( Actions\ModuleOptionsSave::SLUG )
						]
					],
					'super_search'  => [
						'vars' => [
							'render_slug' => Actions\Render\Components\SuperSearchResults::SLUG,
						],
					],
					'select_search' => [
						'ajax'    => [
							'select_search' => ActionData::Build( Actions\PluginSuperSearch::SLUG )
						],
						'strings' => [
							'enter_at_least_3_chars' => __( 'Search using whole words of at least 3 characters...' ),
							'placeholder'            => sprintf( '%s (%s)',
								__( 'Search for anything', 'wp-simple-firewall' ),
								'e.g. '.implode( ', ', [
									__( 'IPs', 'wp-simple-firewall' ),
									__( 'options', 'wp-simple-firewall' ),
									__( 'tools', 'wp-simple-firewall' ),
									__( 'help', 'wp-simple-firewall' ),
								] )
							),
						]
					],
				],
				'strings'    => [
					'select_action'            => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
					'are_you_sure'             => __( 'Are you sure?', 'wp-simple-firewall' ),
					'absolutely_sure'          => __( 'Are you absolutely sure?', 'wp-simple-firewall' ),
					'downloading_file'         => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
					'downloading_file_problem' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
				],
			]
		];

		$locals[] = [
			'global-plugin',
			'icwp_wpsf_vars_globalplugin',
			[
				'vars' => [
					'ajax_render'      => ActionData::Build( Actions\AjaxRender::SLUG ),
					'dashboard_widget' => [
						'ajax' => [
							'render_dashboard_widget' => Actions\Render\Components\DashboardWidget::SLUG
						]
					],
					'notices'          => [
						'ajax' => [
							'auto_db_repair'  => ActionData::Build( Actions\PluginAutoDbRepair::SLUG ),
							'delete_forceoff' => ActionData::Build( Actions\PluginDeleteForceOff::SLUG ),
						]
					]
				],
			]
		];

		$req = Services::Request();
		$opts = $this->getOptions();
		$runCheck = ( $req->ts() - $opts->getOpt( 'ipdetect_at' ) > WEEK_IN_SECONDS*4 )
					|| ( Services::WpUsers()->isUserAdmin() && !empty( $req->query( 'shield_check_ip_source' ) ) );
		if ( $runCheck ) {
			$opts->setOpt( 'ipdetect_at', $req->ts() );
			$locals[] = [
				'shield/ip_detect',
				'icwp_wpsf_vars_ipdetect',
				[
					'url'     => 'https://net.getshieldsecurity.com/wp-json/apto-snapi/v2/tools/what_is_my_ip',
					'ajax'    => ActionData::Build( Actions\PluginIpDetect::SLUG ),
					'flags'   => [
						'silent' => empty( $req->query( 'shield_check_ip_source' ) ),
					],
					'strings' => [
						'source_found' => __( 'Valid visitor IP address source discovered.', 'wp-simple-firewall' ),
						'ip_source'    => __( 'IP Source', 'wp-simple-firewall' ),
						'reloading'    => __( 'Please reload the page.', 'wp-simple-firewall' ),
					],
				]
			];
		}

		return $locals;
	}

	private function createAdminMenu() {
		$con = $this->getCon();
		$menu = $con->cfg->menu;

		if ( $menu[ 'top_level' ] ) {

			$this->pageHookSuffix = add_menu_page(
				$con->getHumanName(),
				$con->labels->MenuTitle,
				$con->getBasePermissions(),
				$this->getPrimaryMenuSlug(),
				[ $this, 'displayModuleAdminPage' ],
				$con->labels->icon_url_16x16
			);

			if ( $menu[ 'has_submenu' ] ) {
				$this->addSubMenuItems();
			}

			if ( $menu[ 'do_submenu_fix' ] ) {
				global $submenu;
				$menuID = $this->getPrimaryMenuSlug();
				if ( isset( $submenu[ $menuID ] ) ) {
					unset( $submenu[ $menuID ][ 0 ] );
				}
				else {
					// remove entire top-level menu if no submenu items - ASSUMES this plugin MUST have submenu or no menu at all
					remove_menu_page( $menuID );
				}
			}
		}
	}

	private function createNetworkAdminMenu() {
		$this->createAdminMenu();
	}

	protected function addSubMenuItems() {
		$con = $this->getCon();

		$navs = [
			PluginURLs::NAV_OVERVIEW       => __( 'Security Dashboard', 'wp-simple-firewall' ),
			PluginURLs::NAV_IP_RULES       => __( 'IP Manager', 'wp-simple-firewall' ),
			PluginURLs::NAV_SCANS_RESULTS  => __( 'Scans', 'wp-simple-firewall' ),
			PluginURLs::NAV_ACTIVITY_LOG   => __( 'Activity', 'wp-simple-firewall' ),
			PluginURLs::NAV_TRAFFIC_VIEWER => __( 'Traffic', 'wp-simple-firewall' ),
			PluginURLs::NAV_OPTIONS_CONFIG => __( 'Configuration', 'wp-simple-firewall' ),
		];
		if ( !$this->getCon()->isPremiumActive() ) {
			$navs[ PluginURLs::NAV_LICENSE ] = sprintf( '<span class="shield_highlighted_menu">%s</span>', 'ShieldPRO' );
		}

		$currentNav = (string)Services::Request()->query( Constants::NAV_ID );
		foreach ( $navs as $submenuNavID => $submenuTitle ) {

			$markupTitle = sprintf( '<span style="color:#fff;font-weight: 600">%s</span>', $submenuTitle );
			$doMarkupTitle = $currentNav === $submenuNavID
							 || ( $submenuNavID === PluginURLs::NAV_OVERVIEW
								  && !isset( $navs[ $currentNav ] )
								  && in_array( $currentNav, PluginURLs::GetAllNavs() ) );

			add_submenu_page(
				$this->getPrimaryMenuSlug(),
				sprintf( '%s | %s', $submenuTitle, $this->getCon()->getHumanName() ),
				$doMarkupTitle ? $markupTitle : $submenuTitle,
				$con->getBasePermissions(),
				$con->prefix( $submenuNavID ),
				[ $this, 'displayModuleAdminPage' ]
			);
		}
	}

	public function displayModuleAdminPage() {
		echo $this->getCon()->action_router->render( Actions\Render\PageAdminPlugin::SLUG );
	}

	private function getPrimaryMenuSlug() :string {
		return $this->getCon()->getModule_Plugin()->getModSlug();
	}
}