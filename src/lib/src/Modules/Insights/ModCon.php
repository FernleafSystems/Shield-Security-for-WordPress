<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Components;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	protected function setupCustomHooks() {
		add_action( 'admin_footer', function () {
			/** @var UI $UI */
			$UI = $this->getUIHandler();
			$UI->printAdminFooterItems();
		}, 100, 0 );
	}

	protected function onModulesLoaded() {
		$this->handleCustomRedirection();
	}

	private function handleCustomRedirection() {
		$con = $this->getCon();
		if ( !Services::WpGeneral()->isAjax() && is_admin() ) {
			if ( !$con->isModulePage() && $con->getModule_Plugin()->getActivateLength() < 5 ) {
				Services::Response()->redirect( $this->getUrl_SubInsightsPage( 'merlin' ) );
			}
			elseif ( $this->getAdminPage()->isCurrentPage() && empty( $this->getCurrentInsightsPage() ) ) {
				Services::Response()->redirect( $con->getPluginUrl_DashboardHome() );
			}
		}
	}

	public function getMainWpData() :array {
		return array_merge( parent::getMainWpData(), [
			'grades' => [
				'integrity' => ( new Components() )
					->setCon( $this->getCon() )
					->getComponent( 'all' )
			]
		] );
	}

	public function getUrl_IpAnalysis( string $ip ) :string {
		return add_query_arg( [ 'analyse_ip' => $ip ], $this->getUrl_IPs() );
	}

	public function getUrl_ActivityLog() :string {
		return $this->getUrl_SubInsightsPage( 'audit_trail' );
	}

	public function getUrl_IPs() :string {
		return $this->getUrl_SubInsightsPage( 'ips' );
	}

	public function getUrl_ScansResults() :string {
		return $this->getUrl_SubInsightsPage( 'scans_results' );
	}

	public function getUrl_ScansRun() :string {
		return $this->getUrl_SubInsightsPage( 'scans_run' );
	}

	public function getUrl_Sessions() :string {
		return $this->getUrl_SubInsightsPage( 'users' );
	}

	public function getUrl_SubInsightsPage( string $inavPage, string $subNav = '' ) :string {
		return add_query_arg(
			array_filter( [
				'inav'   => sanitize_key( $inavPage ),
				'subnav' => sanitize_key( $subNav ),
			] ),
			$this->getUrl_AdminPage()
		);
	}

	public function getCurrentInsightsPage() :string {
		return (string)Services::Request()->query( 'inav' );
	}

	public function getScriptLocalisations() :array {
		$locals = parent::getScriptLocalisations();

		$locals[] = [
			'plugin',
			'icwp_wpsf_vars_insights',
			[
				'strings' => [
					'select_action'   => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
					'are_you_sure'    => __( 'Are you sure?', 'wp-simple-firewall' ),
					'absolutely_sure' => __( 'Are you absolutely sure?', 'wp-simple-firewall' ),
				],
			]
		];
		$locals[] = [
			'shield/merlin',
			'merlin',
			[
				'ajax' => [
					'merlin_action' => $this->getAjaxActionData( 'merlin_action' )
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
					'dynamic_load' => $this->getAjaxActionData( 'dynamic_load' )
				]
			]
		];

		return $locals;
	}

	public function getCustomScriptEnqueues() :array {
		$enq = [
			Enqueue::CSS => [],
			Enqueue::JS  => [],
		];

		$con = $this->getCon();
		$inav = $this->getCurrentInsightsPage();
		if ( empty( $inav ) ) {
			$inav = 'overview';
		}

		if ( $con->getIsPage_PluginAdmin() ) {
			switch ( $inav ) {

				case 'importexport':
					$enq[ Enqueue::JS ][] = 'shield/import';
					break;

				case 'overview':
					$enq[ Enqueue::JS ] = [
						'ip_detect'
					];
					break;

				case 'reports':
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

				case 'merlin':
					$enq[ Enqueue::JS ][] = 'shield/merlin';
					$enq[ Enqueue::CSS ][] = 'shield/merlin';
					break;

				case 'wizard':
					$enq[ Enqueue::JS ][] = 'shield/wizard';
					$enq[ Enqueue::CSS ][] = 'shield/wizard';
					break;

				case 'notes':
				case 'scans_results':
				case 'scans_run':
				case 'audit':
				case 'audit_trail':
				case 'traffic':
				case 'ips':
				case 'debug':
				case 'users':
				case 'stats':

					$enq[ Enqueue::JS ][] = 'shield/tables';
					if ( in_array( $inav, [ 'scans_results', 'scans_run' ] ) ) {
						$enq[ Enqueue::JS ][] = 'shield/scans';
					}
					break;
			}
		}

		return $enq;
	}
}