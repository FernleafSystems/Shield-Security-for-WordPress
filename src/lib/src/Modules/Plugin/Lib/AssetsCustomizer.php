<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	PluginAutoDbRepair,
	PluginDeleteForceOff
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AssetsCustomizer {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return ( is_admin() || is_network_admin() ) && !Services::WpGeneral()->isAjax();
	}

	protected function run() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			return $this->customEnqueues( $enqueues );
		} );
		add_filter( 'shield/custom_localisations', function ( array $localz ) {
			return $this->localiseScripts( $localz );
		} );
	}

	private function customEnqueues( array $enq ) :array {

		if ( $this->isIpAutoDetectRequired() ) {
			$enq[ Enqueue::JS ][] = 'ip_detect';
		}

		if ( self::con()->isPluginAdminPageRequest() ) {
			$nav = Services::Request()->query( Constants::NAV_ID );
			switch ( $nav ) {

				case PluginURLs::NAV_IMPORT_EXPORT:
					$enq[ Enqueue::JS ][] = 'shield/import';
					break;
				case PluginURLs::NAV_OVERVIEW:
					break;
				case PluginURLs::NAV_REPORTS:
					$enq[ Enqueue::JS ] = \array_merge( $enq[ Enqueue::JS ], [
						'chartist',
						'chartist-plugin-legend',
						'shield/charts',
					] );
					$enq[ Enqueue::CSS ] = \array_merge( $enq[ Enqueue::CSS ], [
						'chartist',
						'chartist-plugin-legend',
						'shield/charts'
					] );
					break;
				case PluginURLs::NAV_WIZARD:
					$enq[ Enqueue::JS ][] = 'shield/merlin';
					$enq[ Enqueue::CSS ][] = 'shield/merlin';
					break;

				default:
					$enq[ Enqueue::JS ][] = 'shield/tables';
					if ( \in_array( $nav, [ PluginURLs::NAV_SCANS_RESULTS, PluginURLs::NAV_SCANS_RUN ] ) ) {
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
		return \array_merge( $locals, \array_filter( [
			$this->shieldPluginGlobal(),
			$this->shieldPlugin(),
			$this->navigation(),
			$this->ipAutoDetect(),
			$this->tourManager(),
			$this->merlin(),
		] ) );
	}

	private function merlin() :array {
		return [
			'shield/merlin',
			'merlin',
			[
				'ajax' => [
					'merlin_action' => ActionData::Build( Actions\MerlinAction::class )
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
	}

	private function navigation() :array {
		return [
			'shield/navigation',
			'shield_vars_navigation',
			[
				'ajax' => [
					'dynamic_load' => ActionData::Build( Actions\DynamicPageLoad::class )
				]
			]
		];
	}

	private function tourManager() :array {
		$tourManager = new TourManager();
		return [
			'shield/tours',
			'shield_vars_tourmanager',
			[
				'ajax'   => ActionData::Build( Actions\PluginMarkTourFinished::class ),
				'tours'  => $tourManager->getAllTours(),
				'states' => $tourManager->getStates(),
			]
		];
	}

	private function shieldPlugin() :array {
		$con = self::con();
		return [
			'plugin',
			'icwp_wpsf_vars_plugin',
			[
				'components' => [
					'helpscout'     => [
						'beacon_id' => $con->isPremiumActive() ? 'db2ff886-2329-4029-9452-44587df92c8c' : 'aded6929-af83-452d-993f-a60c03b46568',
						'visible'   => $con->isPluginAdminPageRequest()
					],
					'ip_analysis'   => [
						'ajax' => [
							'ip_analyse_action' => ActionData::Build( Actions\IpAnalyseAction::class ),
						]
					],
					'ip_rules'      => [
						'ajax'    => [
							'ip_rule_add_submit' => ActionData::Build( Actions\IpRuleAddSubmit::class ),
							'ip_rule_delete'     => ActionData::Build( Actions\IpRuleDelete::class ),
						],
						'strings' => [
							'are_you_sure' => __( 'Are you sure you want to delete this IP Rule?', 'wp-simple-firewall' ),
						],
					],
					'offcanvas'     => [
						'ip_analysis'        => Actions\Render\Components\OffCanvas\IpAnalysis::SLUG,
						'form_ip_rule_add'   => Actions\Render\Components\OffCanvas\IpRuleAddForm::SLUG,
						'form_report_create' => Actions\Render\Components\OffCanvas\FormReportCreate::SLUG,
						'meter_analysis'     => Actions\Render\Components\OffCanvas\MeterAnalysis::SLUG,
						'mod_config'         => Actions\Render\Components\OffCanvas\ModConfig::SLUG,
					],
					'mod_options'   => [
						'ajax' => [
							'mod_options_save' => ActionData::Build( Actions\ModuleOptionsSave::class )
						]
					],
					'super_search'  => [
						'vars' => [
							'render_slug' => Actions\Render\Components\SuperSearchResults::SLUG,
						],
					],
					'select_search' => [
						'ajax'    => [
							'select_search' => ActionData::Build( Actions\PluginSuperSearch::class )
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
	}

	private function shieldPluginGlobal() :array {
		return [
			'global-plugin',
			'icwp_wpsf_vars_globalplugin',
			[
				'vars' => [
					'ajax_render'      => ActionData::Build( Actions\AjaxRender::class ),
					'dashboard_widget' => [
						'ajax' => [
							'render_dashboard_widget' => Actions\Render\Components\DashboardWidget::SLUG
						]
					],
					'notices'          => [
						'ajax' => [
							PluginAutoDbRepair::SLUG   => ActionData::Build( PluginAutoDbRepair::class ),
							PluginDeleteForceOff::SLUG => ActionData::Build( PluginDeleteForceOff::class ),
						]
					]
				],
			]
		];
	}

	private function ipAutoDetect() :?array {
		$req = Services::Request();

		$custom = null;
		if ( $this->isIpAutoDetectRequired() ) {
			self::con()->getModule_Plugin()->getOptions()->setOpt( 'ipdetect_at', $req->ts() );
			$custom = [
				'shield/ip_detect',
				'icwp_wpsf_vars_ipdetect',
				[
					'url'     => 'https://net.getshieldsecurity.com/wp-json/apto-snapi/v2/tools/what_is_my_ip',
					'ajax'    => ActionData::Build( Actions\PluginIpDetect::class ),
					'flags'   => [
						'quiet' => empty( $req->query( 'shield_check_ip_source' ) ),
					],
					'strings' => [
						'source_found' => __( 'Valid visitor IP address source discovered.', 'wp-simple-firewall' ),
						'ip_source'    => __( 'IP Source', 'wp-simple-firewall' ),
						'reloading'    => __( 'Please reload the page.', 'wp-simple-firewall' ),
					],
				]
			];
		}

		return $custom;
	}

	private function isIpAutoDetectRequired() :bool {
		$req = Services::Request();
		$optsPlugin = self::con()->getModule_Plugin()->getOptions();
		return ( Services::Request()->ts() - $optsPlugin->getOpt( 'ipdetect_at' ) > \MONTH_IN_SECONDS )
			   || ( Services::WpUsers()->isUserAdmin() && !empty( $req->query( 'shield_check_ip_source' ) ) );
	}
}
