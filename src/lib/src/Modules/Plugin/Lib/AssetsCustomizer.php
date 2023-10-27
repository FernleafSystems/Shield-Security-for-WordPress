<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\{
	ForActivityLog,
	ForIpRules,
	ForSessions,
	ForTraffic,
	Scans\ForMalware,
	Scans\ForPluginTheme,
	Scans\ForWordpress
};
use FernleafSystems\Wordpress\Services\Services;

class AssetsCustomizer {

	use ExecOnce;
	use PluginControllerConsumer;

	private $hook = '';

	private $handles = [];

	/**
	 * @var ?array
	 */
	private $components = null;

	protected function canRun() :bool {
		return !Services::WpGeneral()->isAjax();
	}

	protected function run() {
		add_filter( 'shield/custom_localisations', function ( array $locals, string $hook = '', array $handles = [] ) {
			$this->hook = $hook;
			$this->handles = $handles;
			return \array_merge( $locals, \array_filter( $this->buildForComponents() ) );
		}, 10, 3 );
	}

	private function buildForComponents() :array {
		$data = [];

		$allComponents = $this->components();
		foreach ( $this->handles ?? [] as $handle ) {
			$components = \array_filter(
				$allComponents,
				function ( array $comp ) use ( $handle ) {
					return \in_array( $handle, $comp[ 'handles' ] )
						   && ( !isset( $comp[ 'required' ] ) || $comp[ 'required' ] );
				}
			);

			if ( !empty( $components ) ) {
				if ( empty( $data[ $handle ] ) ) {
					$data[ $handle ] = [
						$handle,
						'shield_vars_'.$handle,
						[
							'service' => [
								'strings' => [
									'select_action'   => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
									'are_you_sure'    => __( 'Are you sure?', 'wp-simple-firewall' ),
									'absolutely_sure' => __( 'Are you absolutely sure?', 'wp-simple-firewall' ),
								],
							],
							'strings' => [
								'select_action'   => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
								'are_you_sure'    => __( 'Are you sure?', 'wp-simple-firewall' ),
								'absolutely_sure' => __( 'Are you absolutely sure?', 'wp-simple-firewall' ),
							],
							'comps'   => \array_map( function ( array $comp ) {
								return \is_callable( $comp[ 'data' ] ?? null ) ? \call_user_func( $comp[ 'data' ] ) : $comp[ 'data' ];
							}, $components ),
						]
					];
				}
			}
		}

		return $data;
	}

	private function components() :array {
		$con = self::con();
		return apply_filters( 'shield/custom_localisations/components', [
			'badge'            => [
				'key'     => 'badge',
				'handles' => [
					'badge',
				],
				'data'    => function () {
					return [
						'ajax' => [
							'plugin_badge_close' => ActionData::Build( Actions\PluginBadgeClose::class ),
						],
					];
				},
			],
			'charts'           => [
				'key'     => 'charts',
				'handles' => [
					'main',
				],
				'data'    => function () {
					return [
						'summary_charts' => [
							'ajax'   => [
								'render_summary_chart' => ActionData::Build( Actions\ReportingChartSummary::class ),
							],
							'charts' => \array_map(
								function ( string $event ) {
									return [
										'event_id'      => $event,
										'init_render'   => true,
										'show_title'    => false,
										'req_params'    => [
											'interval'       => 'daily',
											'events'         => [ $event ],
											'combine_events' => true
										],
										'chart_options' => [
											'axisX' => [
												'showLabel' => false,
											]
										],
									];
								},
								[
									'login_block',
									'bot_blocks',
									'ip_offense',
									'conn_kill',
									'ip_blocked',
									'comment_block',
								]
							),
						],
						'custom_charts'  => [
							'ajax' => [
								'render_custom_chart' => ActionData::Build( Actions\ReportingChartCustom::class ),
							],
						],
					];
				},
			],
			'dashboard_widget' => [
				'key'     => 'dashboard_widget',
				'handles' => [
					'wpadmin',
				],
				'data'    => [
					'ajax' => [
						'render' => ActionData::BuildAjaxRender( Components\Widgets\WpDashboardSummary::class ),
					]
				],
			],
			'file_locker'      => [
				'key'     => 'file_locker',
				'handles' => [
					'main',
				],
				'data'    => function () {
					return [
						'ajax' => [
							'file_action' => ActionData::Build( Actions\ScansFileLockerAction::class ),
							'render_diff' => ActionData::BuildAjaxRender( Components\Scans\ScansFileLockerDiff::class ),
						],
					];
				},
			],
			'helpscout'        => [
				'key'     => 'helpscout',
				'handles' => [
					'main',
				],
				'data'    => [
					'beacon_id' => $con->isPremiumActive() ? 'db2ff886-2329-4029-9452-44587df92c8c' : 'aded6929-af83-452d-993f-a60c03b46568',
					'visible'   => $con->isPluginAdminPageRequest()
				],
			],
			'import' => [
				'key' => 'import',
				'handles' => [
					'main',
				],
				'data'    => function () {
					return [
						'ajax' => [
							'import_from_site' => ActionData::Build( Actions\PluginImportFromSite::class ),
						]
					];
				},
			],
			'ip_analyse'       => [
				'key'     => 'ip_analyse',
				'handles' => [
					'main',
				],
				'data'    => [
					'ajax' => [
						'action'           => ActionData::Build( Actions\IpAnalyseAction::class ),
						'render_offcanvas' => ActionData::BuildAjaxRender( Components\OffCanvas\IpAnalysis::class ),
					],
				],
			],
			'ip_detect'        => [
				'key'     => 'ip_detect',
				'handles' => [
					'main',
					'wpadmin',
				],
				'data'    => function () {
					$con = self::con();
					$con->getModule_Plugin()->opts()->setOpt( 'ipdetect_at', Services::Request()->ts() );
					return [
						'url'     => 'https://net.getshieldsecurity.com/wp-json/apto-snapi/v2/tools/what_is_my_ip',
						'ajax'    => ActionData::Build( Actions\PluginIpDetect::class ),
						'flags'   => [
							'is_check_required' => $this->isIpAutoDetectRequired(),
							'quiet'             => empty( Services::Request()->query( 'shield_check_ip_source' ) ),
						],
						'strings' => [
							'source_found' => __( 'Valid visitor IP address source discovered.', 'wp-simple-firewall' ),
							'ip_source'    => __( 'IP Source', 'wp-simple-firewall' ),
							'reloading'    => __( 'Please reload the page.', 'wp-simple-firewall' ),
						],
					];
				},
			],
			'ip_rules'         => [
				'key'     => 'ip_rules',
				'handles' => [
					'main',
				],
				'data'    => function () {
					return [
						'ajax'    => [
							'add_form_submit'  => ActionData::Build( Actions\IpRuleAddSubmit::class ),
							'delete'           => ActionData::Build( Actions\IpRuleDelete::class ),
							'render_offcanvas' => ActionData::BuildAjaxRender( Components\OffCanvas\IpRuleAddForm::class ),
						],
						'strings' => [
							'are_you_sure' => __( 'Are you sure you want to delete this IP Rule?', 'wp-simple-firewall' ),
						],
					];
				},
			],
			'leanbe'           => [
				'key'     => 'leanbe',
				'handles' => [
					'main',
				],
				'data'    => [
					'vars' => [
						'widget_key' => '62e5437476b962001357b06d',
					],
				],
			],
			'login_guard'      => [
				'key'     => 'login_guard',
				'handles' => [
					'login_guard',
				],
				'data'    => function () {
					$mod = self::con()->getModule_LoginGuard();
					/** @var LoginGuard\Options $opts */
					$opts = $mod->opts();
					return [
						'form_selectors' => $opts->getAntiBotFormSelectors(),
						'uniq'           => \preg_replace( '#[^\da-zA-Z]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
						'cbname'         => $mod->getGaspKey(),
						'strings'        => [
							'label'   => $mod->getTextImAHuman(),
							'alert'   => $mod->getTextPleaseCheckBox(),
							'loading' => __( 'Loading', 'wp-simple-firewall' )
						],
						'flags'          => [
							'gasp' => $opts->isEnabledGaspCheck(),
						]
					];
				},
			],
			'license'          => [
				'key'      => 'license',
				'required' => PluginNavs::IsNavs( PluginNavs::NAV_LICENSE, PluginNavs::SUBNAV_LICENSE_CHECK ),
				'handles'  => [
					'main',
				],
				'data'     => function () {
					return [
						'ajax' => [
							'lookup' => ActionData::Build( Actions\LicenseLookup::class ),
							'clear'  => ActionData::Build( Actions\LicenseClear::class ),
							'debug'  => ActionData::Build( Actions\LicenseCheckDebug::class )
						],
					];
				},
			],
			'merlin'           => [
				'key'      => 'merlin',
				'required' => PluginNavs::IsNavs( PluginNavs::NAV_WIZARD, PluginNavs::SUBNAV_WIZARD_WELCOME ),
				'handles'  => [
					'main',
				],
				'data'     => function () {
					return [
						'ajax' => [
							'action' => ActionData::Build( Actions\MerlinAction::class )
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
					];
				},
			],
			'mod_options'      => [
				'key'     => 'mod_options',
				'handles' => [
					'main',
				],
				'data'    => function () {
					return [
						'ajax' => [
							'form_save'        => ActionData::Build( Actions\ModuleOptionsSave::class ),
							'render_offcanvas' => ActionData::BuildAjaxRender( Components\OffCanvas\ModConfig::class ),
						]
					];
				},
			],
			'navi'             => [
				'key'     => 'navi',
				'handles' => [
					'main',
				],
				'data'    => function () {
					return [
						'ajax' => [
							'dynamic_load' => ActionData::Build( Actions\DynamicPageLoad::class ),
						],
					];
				},
			],
			'notices'          => [
				'key'     => 'notices',
				'handles' => [
					'main',
					'wpadmin',
				],
				'data'    => function () {
					return [
						'ajax' => [
							'resend_verification_email'        => ActionData::Build( Actions\MfaEmailSendVerification::class ),
							'profile_email2fa_disable'         => ActionData::Build( Actions\MfaEmailDisable::class ),
							Actions\DismissAdminNotice::SLUG   => ActionData::Build( Actions\DismissAdminNotice::class ),
							Actions\PluginSetTracking::SLUG    => ActionData::Build( Actions\PluginSetTracking::class ),
							Actions\PluginAutoDbRepair::SLUG   => ActionData::Build( Actions\PluginAutoDbRepair::class ),
							Actions\PluginDeleteForceOff::SLUG => ActionData::Build( Actions\PluginDeleteForceOff::class ),
						],
					];
				},
			],
			'offcanvas'        => [
				'key'     => 'offcanvas',
				'handles' => [
					'main',
				],
				'data'    => [
					'ajax' => [
						'render' => ActionData::BuildAjaxRender(),
					],
				],
			],
			'progress_meters'  => [
				'key'      => 'progress_meters',
				'required' => PluginNavs::GetNav() === PluginNavs::NAV_DASHBOARD,
				'handles'  => [
					'main',
				],
				'data'     => function () {
					return [
						'ajax' => [
							'render_offcanvas' => ActionData::BuildAjaxRender( Components\OffCanvas\MeterAnalysis::class ),
						],
					];
				},
			],
			'reports'          => [
				'key'      => 'reports',
				'required' => PluginNavs::IsNavs( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST ),
				'handles'  => [
					'main',
				],
				'data'     => function () {
					return self::con()->getModule_Plugin()->getReportingController()->getCreateReportFormVars();
				},
			],
			'scans'            => [
				'key'      => 'scans',
				'required' => PluginNavs::GetNav() === PluginNavs::NAV_SCANS,
				'handles'  => [
					'main',
				],
				'data'     => function () {
					$con = self::con();
					return [
						'ajax'  => [
							'check'            => ActionData::Build( Actions\ScansCheck::class ),
							'start'            => ActionData::Build( Actions\ScansStart::class ),
							'results_action'   => ActionData::Build( Actions\ScanResultsTableAction::class ),
							'malai_file_query' => ActionData::Build( Actions\ScansMalaiFileQuery::class ),
						],
						'flags' => [
							'initial_check' => $con->getModule_HackGuard()
												   ->getScanQueueController()
												   ->hasRunningScans(),
						],
						'hrefs' => [
							'results' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
						],
						'vars'  => [
							'scan_results_tables' => [
								'malware'      => [
									'ajax' => [
										'render_item_analysis' => ActionData::BuildAjaxRender( Components\Scans\ItemAnalysis\Container::class ),
										'table_action'         => ActionData::Build( Actions\ScanResultsTableAction::class, true, [
											'type' => 'malware',
											'file' => 'malware',
										] ),
									],
									'vars' => [
										'table_selector'  => '#ShieldTable-ScanResultsMalware',
										'datatables_init' => ( new ForMalware() )->buildRaw(),
									],
								],
								'wordpress'    => [
									'ajax' => [
										'render_item_analysis' => ActionData::BuildAjaxRender( Components\Scans\ItemAnalysis\Container::class ),
										'table_action'         => ActionData::Build( Actions\ScanResultsTableAction::class, true, [
											'type' => 'wordpress',
											'file' => 'wordpress',
										] ),
									],
									'vars' => [
										'table_selector'  => '#ShieldTable-ScanResultsWordpress',
										'datatables_init' => ( new ForWordpress() )->buildRaw(),
									],
								],
								'plugin_theme' => [
									'ajax'    => [
										'render_item_analysis' => ActionData::BuildAjaxRender( Components\Scans\ItemAnalysis\Container::class ),
										'table_action'         => ActionData::Build( Actions\ScanResultsTableAction::class ),
									],
									'strings' => [
										'select_action'            => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
										'are_you_sure'             => __( 'Are you sure?', 'wp-simple-firewall' ),
										'absolutely_sure'          => __( 'Are you absolutely sure?', 'wp-simple-firewall' ),
										'downloading_file'         => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
										'downloading_file_problem' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
									],
									'vars'    => [
										'table_selector'  => '.shield-section-datatable .table-for-plugintheme',
										'datatables_init' => ( new ForPluginTheme() )->buildRaw(),
									],
								],
							],
						],
					];
				},
			],
			'super_search'     => [
				'key'     => 'super_search',
				'handles' => [
					'main',
				],
				'data'    => function () {
					return [
						'ajax'    => [
							'render_search_results' => ActionData::BuildAjaxRender( Components\SuperSearchResults::class ),
							'select_search'         => ActionData::Build( Actions\PluginSuperSearch::class ),
						],
						'strings' => [
							'enter_at_least_3_chars' => __( 'Search using whole words of at least 3 characters...' ),
							'placeholder'            => sprintf( '%s (%s)',
								__( 'Search for anything', 'wp-simple-firewall' ),
								'e.g. '.\implode( ', ', [
									__( 'IPs', 'wp-simple-firewall' ),
									__( 'options', 'wp-simple-firewall' ),
									__( 'tools', 'wp-simple-firewall' ),
									__( 'help', 'wp-simple-firewall' ),
								] )
							),
						],
					];
				},
			],
			'tables'           => [
				'key'     => 'tables',
				'handles' => [
					'main',
				],
				'data'    => function () {
					$data = [];
					if ( PluginNavs::IsNavs( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ) ) {
						$data[ 'activity' ] = [
							'ajax' => [
								'table_action' => ActionData::Build( Actions\ActivityLogTableAction::class ),
							],
							'vars' => [
								'datatables_init' => ( new ForActivityLog() )->buildRaw(),
							]
						];
					}
					elseif ( PluginNavs::IsNavs( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ) ) {
						$data[ 'ip_rules' ] = [
							'ajax' => [
								'table_action' => ActionData::Build( Actions\IpRulesTableAction::class ),
							],
							'vars' => [
								'datatables_init' => ( new ForIpRules() )->buildRaw(),
							]
						];
					}
					elseif ( PluginNavs::IsNavs( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ) ) {
						$data[ 'traffic' ] = [
							'ajax' => [
								'table_action' => ActionData::Build( Actions\TrafficLogTableAction::class ),
							],
							'vars' => [
								'datatables_init' => ( new ForTraffic() )->buildRaw(),
							]
						];
					}
					elseif ( PluginNavs::IsNavs( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_SESSIONS ) ) {
						$data[ 'sessions' ] = [
							'ajax' => [
								'table_action' => ActionData::Build( Actions\SessionsTableAction::class ),
							],
							'vars' => [
								'datatables_init' => ( new ForSessions() )->buildRaw(),
							]
						];
					}
					return $data;
				}
			],
			'tours'            => [
				'key'     => 'tours',
				'handles' => [
					'main',
				],
				'data'    => function () {
					$tourManager = new TourManager();
					return [
						'ajax' => [
							'finished' => ActionData::Build( Actions\PluginMarkTourFinished::class ),
						],
						'vars' => [
							'tours'  => $tourManager->getAllTours(),
							'states' => $tourManager->getStates(),
						]
					];
				},
			],
			'traffic'          => [
				'key'     => 'traffic',
				'handles' => [
					'main',
				],
				'data'    => [
					'ajax' => [
						'render_live' => ActionData::BuildAjaxRender( Components\Traffic\TrafficLiveLogs::class ),
					],
				],
			],
		], $this->hook, $this->handles );
	}

	private function isIpAutoDetectRequired() :bool {
		$req = Services::Request();
		$optsPlugin = self::con()->getModule_Plugin()->opts();
		return ( Services::Request()->ts() - $optsPlugin->getOpt( 'ipdetect_at' ) > \MONTH_IN_SECONDS )
			   || ( Services::WpUsers()->isUserAdmin() && !empty( $req->query( 'shield_check_ip_source' ) ) );
	}

	/**
	 * @deprecated 18.5
	 */
	private function customEnqueues( array $enq ) :array {
		return $enq;
	}

	private function shieldPluginGlobal() :array {
		return [];
	}

	/**
	 * @deprecated 18.5
	 */
	private function tourManager() :array {
		return [];
	}

	/**
	 * @deprecated 18.5
	 */
	private function merlin() :array {
		return [];
	}

	/**
	 * @deprecated 18.5
	 */
	private function shieldPlugin() :array {
		return [];
	}

	/**
	 * @deprecated 18.5
	 */
	private function ipAutoDetect() :?array {
		return [];
	}

	/**
	 * @deprecated 18.5
	 */
	private function localiseScripts( array $locals ) :array {
		return [];
	}
}