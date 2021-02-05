<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
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
		$this->maybeRedirectToAdmin();
	}

	private function maybeRedirectToAdmin() {
		$con = $this->getCon();
		$nActiveFor = $con->getModule_Plugin()->getActivateLength();
		if ( !Services::WpGeneral()->isAjax() && is_admin() && !$con->isModulePage() && $nActiveFor < 4 ) {
			Services::Response()->redirect( $this->getUrl_AdminPage() );
		}
	}

	public function getUrl_IpAnalysis( string $ip ) :string {
		return add_query_arg( [ 'analyse_ip' => $ip ], $this->getUrl_SubInsightsPage( 'ips' ) );
	}

	public function getUrl_SubInsightsPage( string $subPage ) :string {
		return add_query_arg(
			[ 'inav' => sanitize_key( $subPage ) ],
			$this->getUrl_AdminPage()
		);
	}

	protected function renderModulePage( array $aData = [] ) :string {
		/** @var UI $UI */
		$UI = $this->getUIHandler();
		return $UI->renderPages();
	}

	public function getScriptLocalisations() :array {
		$con = $this->getCon();
		$locals = parent::getScriptLocalisations();
		$locals[] = [
			$con->prefix( 'plugin' ),
			'icwp_wpsf_vars_insights',
			[
				'strings' => [
					'downloading_file'         => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
					'downloading_file_problem' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
					'select_action'            => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
					'are_you_sure'             => __( 'Are you sure?', 'wp-simple-firewall' ),
				],
			]
		];
		$locals[] = [
			$con->prefix( 'ip_detect' ),
			'icwp_wpsf_vars_ipdetect',
			[ 'ajax' => $con->getModule_Plugin()->getAjaxActionData( 'ipdetect' ) ]
		];

		return $locals;
	}

	public function getCustomScriptEnqueues() :array {
		$enqs = [
			Enqueue::CSS => [],
			Enqueue::JS  => [],
		];

		$con = $this->getCon();
		$iNav = Services::Request()->query( 'inav', 'overview' );
		$oTourManager = $con->getModule_Plugin()->getTourManager();

		switch ( $iNav ) {

			case 'importexport':
				$enqs[ Enqueue::JS ][] = 'shield/import';
				break;

			case 'overview':
			case 'reports':

				$enqs[ Enqueue::JS ] = [
					'chartist.min',
					'chartist-plugin-legend',
					'charts',
					'shuffle',
					'shield-card-shuffle',
					'ip_detect'
				];
				$enqs[ Enqueue::CSS ] = [
					'chartist.min',
					'chartist-plugin-legend'
				];

				if ( $oTourManager->canShow( 'insights_overview' ) ) {
					$enqs[ Enqueue::JS ][] = 'introjs.min';
					$enqs[ Enqueue::CSS ][] = 'introjs.min';
				}
				break;

			case 'notes':
			case 'scans':
			case 'audit':
			case 'traffic':
			case 'ips':
			case 'debug':
			case 'users':

				$enqs[ Enqueue::JS ][] = 'shield-tables';
				if ( $iNav == 'scans' ) {
					$enqs[ Enqueue::JS ][] = 'shield-scans';
				}
				elseif ( $iNav == 'ips' ) {
					$enqs[ Enqueue::JS ][] = 'shield/ipanalyse';
				}

				if ( in_array( $iNav, [ 'audit', 'traffic' ] ) ) {
					$enqs[ Enqueue::JS ][] = 'bootstrap-datepicker';
					$enqs[ Enqueue::CSS ][] = 'bootstrap-datepicker';
				}
				break;
		}

		return $enqs;
	}

	/**
	 * @deprecated 10.2
	 */
	private function includeScriptIpDetect() {
	}
}