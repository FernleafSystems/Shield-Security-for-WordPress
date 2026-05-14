<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\{
	ForActivityLog,
	ForIpRules,
	ForReports,
	ForSecurityRules,
	ForSessions,
	ForTraffic
};
use FernleafSystems\Wordpress\Services\Services;

class AssetsCustomizer {

	use ExecOnce;
	use PluginControllerConsumer;

	private string $hook = '';

	private array $handles = [];

	protected function canRun() :bool {
		return !Services::WpGeneral()->isAjax();
	}

	protected function run() {
		add_filter( 'shield/custom_enqueue_assets', fn( array $assets ) => $this->buildCustomEnqueueAssets( $assets ) );

		add_filter( 'shield/custom_localisations', function ( array $locals, string $hook = '', array $handles = [] ) {
			return $this->buildCustomLocalisations( $locals, $hook, $handles );
		}, 10, 3 );
	}

	private function buildCustomEnqueueAssets( array $assets ) :array {
		if ( $this->isPluginOnboardingRequired() ) {
			$assets[] = 'plugin_onboarding';
		}
		return \array_unique( $assets );
	}

	private function buildCustomLocalisations( array $locals, string $hook = '', array $handles = [] ) :array {
		$this->hook = $hook;
		$this->handles = $handles;
		return \array_merge( $locals, \array_filter( $this->buildForComponents() ) );
	}

	private function buildForComponents() :array {
		$data = [];

		$all = $this->components();
		foreach ( $this->handles as $handle ) {
			$components = \array_filter( $all,
				fn( array $c ) => \in_array( $handle, $c[ 'handles' ] ) && ( !isset( $c[ 'required' ] ) || $c[ 'required' ] )
			);

			if ( !empty( $components ) && empty( $data[ $handle ] ) ) {
				$data[ $handle ] = [
					$handle,
					'shield_vars_'.$handle,
					[
						'strings' => [
							'select_action'        => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
							'are_you_sure'         => __( 'Are you sure?', 'wp-simple-firewall' ),
							'absolutely_sure'      => __( 'Are you absolutely sure?', 'wp-simple-firewall' ),
							'cancel'               => __( 'Cancel', 'wp-simple-firewall' ),
							'close'                => __( 'Close', 'wp-simple-firewall' ),
							'confirm'              => __( 'Confirm', 'wp-simple-firewall' ),
							'dialog_alert_title'   => __( 'Notice', 'wp-simple-firewall' ),
							'dialog_confirm_title' => __( 'Confirm Action', 'wp-simple-firewall' ),
							'dialog_prompt_title'  => __( 'Information Required', 'wp-simple-firewall' ),
							'request_failed'       => __( 'Request Failed', 'wp-simple-firewall' ),
							'table_loading'        => __( 'Loading table data.', 'wp-simple-firewall' ),
							'scan_repair_limit_exceeded' => __( "Sorry, this tool isn't designed for such large repairs. We recommend completely removing and reinstalling the item.", 'wp-simple-firewall' ),
						],
						'comps'   => \array_map(
							fn( array $c ) => \is_callable( $c[ 'data' ] ?? null ) ? \call_user_func( $c[ 'data' ] ) : $c[ 'data' ],
							$components ),
					]
				];
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
				'data'    => fn() => [
					'ajax' => [
						'plugin_badge_close' => ActionData::Build( Actions\PluginBadgeClose::class ),
					],
				],
			],
			'blockdown'        => [
				'key'     => 'blockdown',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
					'ajax' => [
						Actions\BlockdownFormSubmit::SLUG        => ActionData::Build( Actions\BlockdownFormSubmit::class ),
						Actions\BlockdownDisableFormSubmit::SLUG => ActionData::Build( Actions\BlockdownDisableFormSubmit::class ),
					],
				],
			],
			'reports_trends'   => [
				'key'      => 'reports_trends',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
					'ajax'    => [
						'render_chart' => ActionData::Build( Actions\ReportingChartTrends::class ),
					],
					'strings' => Components\Reports\ChartsTrends::clientStrings(),
				],
			],
			'dashboard_live_monitor' => [
				'key'      => 'dashboard_live_monitor',
				'required' => PluginNavs::IsNavs( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_OVERVIEW ),
				'handles'  => [
					'main',
				],
				'data'     => [
					'ajax' => [
						'batch_requests' => ActionData::Build( Actions\AjaxBatchRequests::class ),
						'set_state'      => ActionData::Build( Actions\DashboardLiveMonitorSetState::class ),
						'render_ticker'  => ActionData::BuildAjaxRender( Components\Widgets\DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
						'render_traffic' => ActionData::BuildAjaxRender( Components\Traffic\TrafficLiveLogs::class, [ 'limit' => 12 ] ),
					],
					'vars' => [
						'poll_interval_ms' => 5000,
						'max_polls'        => 17280,
					],
				],
			],
			'dashboard_widget' => [
				'key'     => 'dashboard_widget',
				'handles' => [
					'wpadmin',
				],
				'data'    => [
					'ajax'    => [
						'render' => ActionData::BuildAjaxRender( Components\Widgets\WpDashboardSummary::class ),
					],
					'strings' => [
						'load_failed' => __( 'There was a problem loading the content.', 'wp-simple-firewall' ),
					],
				],
			],
			'debug_tools'      => [
				'key'     => 'debug_tools',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
					'ajax' => [
						Actions\ToolPurgeProviderIPs::SLUG => ActionData::Build( Actions\ToolPurgeProviderIPs::class ),
					],
				],
			],
			'file_locker'      => [
				'key'     => 'file_locker',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
					'ajax' => [
						'file_action' => ActionData::Build( Actions\ScansFileLockerAction::class ),
						'render_diff' => ActionData::BuildAjaxRender( Components\Scans\ScansFileLockerDiff::class ),
					],
				],
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
			'import'           => [
				'key'     => 'import',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
					'ajax' => [
						'import_from_site' => ActionData::Build( Actions\PluginImportFromSite::class ),
					],
				],
			],
			'investigate_landing' => [
				'key'      => 'investigate_landing',
				'required' => PluginNavs::GetNav() === PluginNavs::NAV_ACTIVITY
							  && (
								  PluginNavs::GetSubNav() === PluginNavs::SUBNAV_ACTIVITY_OVERVIEW
								  || PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::GetSubNav() ) !== ''
							  ),
				'handles'  => [
					'main',
				],
				'data'     => fn() => [
					'ajax' => [
						'batch_requests' => ActionData::Build( Actions\AjaxBatchRequests::class ),
					],
				],
			],
			'actions_queue_landing' => [
				'key'      => 'actions_queue_landing',
				'required' => PluginNavs::IsNavs( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_OVERVIEW ),
				'handles'  => [
					'main',
				],
				'data'     => fn() => [
					'ajax' => [
						'batch_requests' => ActionData::Build( Actions\AjaxBatchRequests::class ),
					],
				],
			],
			'ip_analyse'       => [
				'key'     => 'ip_analyse',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
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
				'data'    => fn() => [
					'url'     => 'https://ip-detect.workers.aptoweb.com',
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
				],
			],
			'ip_rules'         => [
				'key'     => 'ip_rules',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
					'ajax' => [
						'add_form_submit'  => ActionData::Build( Actions\IpRuleAddSubmit::class ),
						'render_offcanvas' => ActionData::BuildAjaxRender( Components\OffCanvas\IpRuleAddForm::class ),
					],
				],
			],
			'license'          => [
				'key'      => 'license',
				'required' => PluginNavs::IsNavs( PluginNavs::NAV_LICENSE, PluginNavs::SUBNAV_LICENSE_CHECK ),
				'handles'  => [
					'main',
				],
				'data'     => fn() => [
					'ajax' => [
						'lookup' => ActionData::Build( Actions\LicenseLookup::class ),
						'clear'  => ActionData::Build( Actions\LicenseClear::class ),
						'debug'  => ActionData::Build( Actions\LicenseCheckDebug::class )
					],
				],
			],
			'merlin'           => [
				'key'      => 'merlin',
				'required' => PluginNavs::IsNavs( PluginNavs::NAV_WIZARD, PluginNavs::SUBNAV_WIZARD_WELCOME ),
				'handles'  => [
					'main',
				],
				'data'     => fn() => [
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
				],
			],
			'misc_hooks'       => [
				'key'      => 'misc_hooks',
				'required' => true,
				'handles'  => [
					'main',
				],
				'data'     => [],
			],
			'mod_options'      => [
				'key'     => 'mod_options',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
					'ajax' => [
						'form_save'           => ActionData::Build( Actions\ModuleOptionsSave::class ),
						'xfer_include_toggle' => ActionData::Build( Actions\OptionTransferIncludeToggle::class ),
					]
				],
			],
			'navi'             => [
				'key'     => 'navi',
				'handles' => [
					'main',
				],
				'data'    => [],
			],
			'notices'          => [
				'key'     => 'notices',
				'handles' => [
					'main',
					'wpadmin',
				],
				'data'    => fn() => [
					'ajax' => [
						'resend_verification_email'             => ActionData::Build( Actions\MfaEmailSendVerification::class ),
						Actions\MfaEmailDisable::SLUG           => ActionData::Build( Actions\MfaEmailDisable::class ),
						Actions\DismissAdminNotice::SLUG        => ActionData::Build( Actions\DismissAdminNotice::class ),
						Actions\PluginSetTracking::SLUG         => ActionData::Build( Actions\PluginSetTracking::class ),
						Actions\PluginAutoDbRepair::SLUG        => ActionData::Build( Actions\PluginAutoDbRepair::class ),
						Actions\PluginDeleteForceOff::SLUG      => ActionData::Build( Actions\PluginDeleteForceOff::class ),
						Actions\TranslationsForceDownload::SLUG => ActionData::Build( Actions\TranslationsForceDownload::class ),
					]
				],
			],
			'rule_builder'     => [
				'key'     => 'rule_builder',
				'handles' => [
					'main',
				],
				'data'    => [
					'ajax' => [
						'render_rule_builder' => ActionData::BuildAjaxRender( Components\Rules\RuleBuilder::class ),
						'rule_builder_action' => ActionData::Build( Actions\RuleBuilderAction::class ),
					],
				],
			],
			'rules_manager'    => [
				'key'     => 'rules_manager',
				'handles' => [
					'main',
				],
				'data'    => [
					'ajax' => [
						'render_rules_manager' => ActionData::BuildAjaxRender( Components\Rules\RulesManager::class ),
						'rules_manager_action' => ActionData::Build( Actions\RulesManagerTableAction::class ),
					],
				],
			],
			'reports'          => [
				'key'      => 'reports',
				'required' => PluginNavs::IsNavs( PluginNavs::NAV_REPORTS, PluginNavs::reportsDefaultWorkspaceSubNav() ),
				'handles'  => [
					'main',
				],
				'data'     => fn() => self::con()->comps->reports->getCreateReportFormVars(),
			],
			'scans'            => [
				'key'      => 'scans',
				'required' => PluginNavs::GetNav() === PluginNavs::NAV_SCANS,
				'handles'  => [
					'main',
				],
				'data'     => fn() => [
					'ajax'  => [
						'check'            => ActionData::Build( Actions\ScansCheck::class ),
						'start'            => ActionData::Build( Actions\ScansStart::class ),
					],
					'flags' => [
						'initial_check' => $con->comps->scans_queue->hasRunningScans(),
					],
					'hrefs' => [
						'actions_queue_scans' => $con->plugin_urls->actionsQueueScans(),
					],
					'strings' => [
						'modal_title'         => __( 'Scan Progress', 'wp-simple-firewall' ),
						'modal_initiating'    => __( 'Preparing scans.', 'wp-simple-firewall' ),
						'modal_wait'          => __( 'Please wait while the scan request starts.', 'wp-simple-firewall' ),
						'modal_error_title'   => __( 'Scan failed.', 'wp-simple-firewall' ),
						'modal_error_message' => __( 'Scan progress could not be updated.', 'wp-simple-firewall' ),
					],
				],
			],
			'super_search'     => [
				'key'     => 'super_search',
				'handles' => [
					'main',
				],
				'data'    => fn() => [
					'ajax'    => [
						'render_search_results' => ActionData::BuildAjaxRender( Components\SuperSearchResults::class ),
						'select_search'         => ActionData::Build( Actions\PluginSuperSearch::class ),
					],
					'strings' => [
						'enter_at_least_3_chars' => __( 'Search using whole words of at least 3 characters...', 'wp-simple-firewall' ),
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
				],
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
								'table_action'     => ActionData::Build( Actions\ActivityLogTableAction::class ),
								'render_offcanvas' => ActionData::BuildAjaxRender( Components\OffCanvas\SearchHelp::class ),
							],
							'vars' => [
								'datatables_init' => ( new ForActivityLog() )->buildRaw(),
							]
						];
					}
					elseif ( PluginNavs::IsNavs( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ) ) {
						$data[ 'ip_rules' ] = [
							'ajax' => [
								'rule_delete'  => ActionData::Build( Actions\IpRuleDelete::class ),
								'table_action' => ActionData::Build( Actions\IpRulesTableAction::class ),
							],
							'strings' => [
								'are_you_sure' => __( 'Are you sure you want to delete this IP Rule?', 'wp-simple-firewall' ),
							],
							'vars' => [
								'datatables_init' => ( new ForIpRules() )->buildRaw(),
							]
						];
					}
					elseif ( PluginNavs::IsNavs( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ) ) {
						$data[ 'traffic' ] = [
							'ajax' => [
								'table_action'     => ActionData::Build( Actions\TrafficLogTableAction::class ),
								'render_offcanvas' => ActionData::BuildAjaxRender( Components\OffCanvas\SearchHelp::class ),
							],
							'vars' => [
								'datatables_init' => ( new ForTraffic() )->buildRaw(),
							]
						];
					}
					elseif (
						PluginNavs::IsNavs( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_SESSIONS )
						|| PluginNavs::IsNavs( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_SESSIONS )
					) {
						$data[ 'sessions' ] = [
							'ajax' => [
								'table_action' => ActionData::Build( Actions\SessionsTableAction::class ),
							],
							'vars' => [
								'datatables_init' => ( new ForSessions() )->buildRaw(),
							]
						];
					}
					elseif ( PluginNavs::IsNavs( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_MANAGE ) ) {
						$data[ 'security_rules' ] = [
							'ajax'    => [
								'table_action' => ActionData::Build( Actions\RulesManagerTableAction::class ),
							],
							'hrefs'   => [
								'create_new' => self::con()->plugin_urls->rulesBuild(),
							],
							'strings' => [
								'no_rules_yet' => sprintf( '%s. %s', __( 'There are no custom security rules', 'wp-simple-firewall' ),
									__( 'Use the link above to create a new one.', 'wp-simple-firewall' ) ),
							],
							'vars'    => [
								'datatables_init' => ( new ForSecurityRules() )->buildRaw(),
							]
						];
					}
					elseif (
						PluginNavs::IsNavs( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_OVERVIEW )
						|| PluginNavs::IsNavs( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST )
					) {
						$data[ 'reports' ] = [
							'ajax' => [
								'table_action' => ActionData::Build( Actions\ReportTableAction::class ),
							],
							'vars' => [
								'datatables_init' => ( new ForReports() )->buildRaw(),
							]
						];
					}
					return $data;
				}
			],
			'testrest'         => [
				'key'     => 'testrest',
				'handles' => [
					'main',
					'wpadmin',
				],
				'data'    => function () {
					$con = self::con();
					/**
					 * This is temporary and only to be used to determine (via telemetry) whether clients can
					 * actually use this method of requests.
					 * @deprecated 18.5
					 */
					$data = $con->opts->optGet( Actions\TestRestFetchRequests::OPT_KEY );
					if ( empty( $data ) || \array_key_exists( 'test_at', $data ) ) {
						$data = [];
					}

					$data = \array_merge( [
						Actions\TestRestFetchRequests::DATA_MAYBE_TEST_AT   => 0,
						Actions\TestRestFetchRequests::DATA_SUCCESS_TEST_AT => 0,
					], $data );

					$now = Services::Request()->ts();

					$run = ( $now - (int)$data[ Actions\TestRestFetchRequests::DATA_MAYBE_TEST_AT ] ) > \DAY_IN_SECONDS;
					if ( $run ) {
						$data[ Actions\TestRestFetchRequests::DATA_MAYBE_TEST_AT ] = $now;
						$con->opts->optSet( Actions\TestRestFetchRequests::OPT_KEY, $data )
								  ->store();
					}

					return [
						'ajax'  => [
							'test_rest' => ActionData::Build( Actions\TestRestFetchRequests::class ),
						],
						'flags' => [
							'can_run' => $run,
						],
					];
				},
			],
			'plugin_onboarding' => [
				'key'      => 'plugin_onboarding',
				'required' => $this->isPluginOnboardingRequired(),
				'handles'  => [
					'plugin_onboarding',
				],
				'data'    => function () {
					$tourManager = new TourManager();
					return [
						'ajax' => [
							'finished' => ActionData::Build( Actions\PluginMarkTourFinished::class ),
						],
						'vars' => [
							'tour' => $tourManager->getTour(),
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
			'zones_manager'    => [
				'key'     => 'zones_manager',
				'handles' => [
					'main',
				],
				'data'    => [
					'ajax' => [
						'batch_requests' => ActionData::Build( Actions\AjaxBatchRequests::class ),
						Components\OffCanvas\ZoneComponentConfig::SLUG => ActionData::BuildAjaxRender( Components\OffCanvas\ZoneComponentConfig::class ),
					],
				],
			],
		], $this->hook, $this->handles );
	}

	private function isIpAutoDetectRequired() :bool {
		$req = Services::Request();
		$since = $req->ts() - self::con()->opts->optGet( 'ipdetect_at' );
		return ( $since > \MONTH_IN_SECONDS
				 || ( self::con()->comps->opts_lookup->ipSource() === 'AUTO_DETECT_IP' && $since > \DAY_IN_SECONDS )
				 || ( Services::WpUsers()->isUserAdmin() && !empty( $req->query( 'shield_check_ip_source' ) ) )
		);
	}

	private function isPluginOnboardingRequired() :bool {
		return ( ( new TourManager() )->getTour()[ 'is_available' ] ?? false ) === true;
	}
}
