<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\{
	DynamicLoad,
	MerlinAction,
	Render\Components\BannerGoPro,
	Render\Components\ToastPlaceholder
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\{
	ActionData,
	ActionRoutingController,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Components;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var ActionRouter\ActionRoutingController
	 */
	private $router;

	protected function onModulesLoaded() {
		// Before IP Block
		add_action( 'init', function () {
			$this->getActionRouter()->execute();
		}, -1 );
	}

	public function getActionRouter() :ActionRouter\ActionRoutingController {
		if ( !isset( $this->router ) ) {
			$this->router = ( new ActionRouter\ActionRoutingController() )->setMod( $this );
		}
		return $this->router;
	}

	protected function setupCustomHooks() {
		add_action( 'admin_footer', function () {
			if ( method_exists( $this, 'getActionRouter' ) ) {
				$AR = $this->getActionRouter();
				echo $AR->render( BannerGoPro::SLUG );
				if ( $this->getCon()->isModulePage() ) {
					echo $AR->render( ToastPlaceholder::SLUG );
				}
			}
		}, 100, 0 );
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
				Constants::NAV_ID     => sanitize_key( $inavPage ),
				Constants::NAV_SUB_ID => sanitize_key( $subNav ),
			] ),
			$this->getUrl_AdminPage()
		);
	}

	public function getUrl_AdminPage() :string {
		return Services::WpGeneral()->getUrl_AdminPage(
			$this->getModSlug(),
			$this->getCon()->getIsWpmsNetworkAdminOnly()
		);
	}

	/**
	 * @return AdminPage
	 */
	public function getAdminPage() {
		if ( !isset( $this->adminPage ) ) {
			$this->adminPage = ( new AdminPage() )->setMod( $this );
		}
		return $this->adminPage;
	}

	public function getCurrentInsightsPage() :string {
		return (string)Services::Request()->query( Constants::NAV_ID );
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
					'dynamic_load' => ActionData::Build( DynamicLoad::SLUG )
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
		$nav = $this->getCurrentInsightsPage();
		if ( empty( $nav ) ) {
			$nav = Constants::ADMIN_PAGE_OVERVIEW;
		}

		if ( $con->getIsPage_PluginAdmin() ) {
			switch ( $nav ) {

				case 'importexport':
					$enq[ Enqueue::JS ][] = 'shield/import';
					break;

				case Constants::ADMIN_PAGE_OVERVIEW:
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
					if ( in_array( $nav, [ 'scans_results', 'scans_run' ] ) ) {
						$enq[ Enqueue::JS ][] = 'shield/scans';
					}
					break;
			}
		}

		return $enq;
	}

	/**
	 * @deprecated 16.2
	 */
	public function createFileDownloadLink( string $downloadID, array $additionalParams = [] ) :string {
		return '';
	}
}