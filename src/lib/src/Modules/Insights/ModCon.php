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
		$activeFor = $con->getModule_Plugin()->getActivateLength();
		if ( !Services::WpGeneral()->isAjax() && is_admin() && !$con->isModulePage() && $activeFor < 4 ) {
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

	protected function renderModulePage( array $data = [] ) :string {
		/** @var UI $UI */
		$UI = $this->getUIHandler();
		return $UI->renderPages();
	}

	public function getScriptLocalisations() :array {
		$con = $this->getCon();
		$modPlugin = $con->getModule_Plugin();

		$locals = parent::getScriptLocalisations();
		$locals[] = [
			'plugin',
			'icwp_wpsf_vars_insights',
			[
				'strings' => [
					'select_action' => __( 'Please select an action to perform.', 'wp-simple-firewall' ),
					'are_you_sure'  => __( 'Are you sure?', 'wp-simple-firewall' ),
				],
			]
		];

		$locals[] = [
			$con->prefix( 'ip_detect' ),
			'icwp_wpsf_vars_ipdetect',
			[ 'ajax' => $modPlugin->getAjaxActionData( 'ipdetect' ) ]
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
		$inav = Services::Request()->query( 'inav' );

		if ( $con->getIsPage_PluginAdmin() && !empty( $inav ) ) {
			switch ( $inav ) {

				case 'importexport':
					$enq[ Enqueue::JS ][] = 'shield/import';
					break;

				case 'overview':
					$enq[ Enqueue::JS ] = [
						'shuffle',
						'shield/shuffle',
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

				case 'notes':
				case 'scans_results':
				case 'scans_run':
				case 'audit':
				case 'traffic':
				case 'ips':
				case 'debug':
				case 'users':

					$enq[ Enqueue::JS ][] = 'shield-tables';
					if ( in_array( $inav, [ 'scans_results', 'scans_run' ] ) ) {
						$enq[ Enqueue::JS ][] = 'shield-scans';
					}
					elseif ( $inav == 'ips' ) {
						$enq[ Enqueue::JS ][] = 'shield/ipanalyse';
					}

					if ( in_array( $inav, [ 'audit', 'traffic' ] ) ) {
						$enq[ Enqueue::JS ][] = 'bootstrap-datepicker';
						$enq[ Enqueue::CSS ][] = 'bootstrap-datepicker';
					}
					break;
			}
		}

		return $enq;
	}
}