<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
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
		$locals = parent::getScriptLocalisations();
		$locals[] = [
			$this->getCon()->prefix( 'plugin' ),
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
		return $locals;
	}

	public function insertCustomJsVars_Admin() {

		if ( $this->isThisModulePage() ) {

			$con = $this->getCon();
			$aStdDepsJs = [ $con->prefix( 'plugin' ) ];
			$iNav = Services::Request()->query( 'inav', 'overview' );

			$oModPlugin = $con->getModule_Plugin();
			$oTourManager = $oModPlugin->getTourManager();
			switch ( $iNav ) {

				case 'importexport':

					$aset = 'shield/import';
					$uniq = $con->prefix( $aset );
					wp_register_script(
						$uniq,
						$con->getPluginUrl_Js( $aset ),
						$aStdDepsJs,
						$con->getVersion(),
						false
					);
					wp_enqueue_script( $uniq );
					break;

				case 'overview':
				case 'reports':

					$aDeps = $aStdDepsJs;

					$aJsAssets = [
						'chartist.min',
						'chartist-plugin-legend',
						'charts',
						'shuffle',
						'shield-card-shuffle'
					];
					if ( $oTourManager->canShow( 'insights_overview' ) ) {
						array_unshift( $aJsAssets, 'introjs.min.js' );
					}
					foreach ( $aJsAssets as $aset ) {
						$uniq = $con->prefix( $aset );
						wp_register_script(
							$uniq,
							$con->getPluginUrl_Js( $aset ),
							$aDeps,
							$con->getVersion(),
							false
						);
						wp_enqueue_script( $uniq );
						$aDeps[] = $uniq;
					}

					$aDeps = [];
					$aCssAssets = [ 'chartist.min', 'chartist-plugin-legend' ];
					if ( $oTourManager->canShow( 'insights_overview' ) ) {
						array_unshift( $aCssAssets, 'introjs.min.css' );
					}
					foreach ( $aCssAssets as $aset ) {
						$uniq = $con->prefix( $aset );
						wp_register_style(
							$uniq,
							$con->getPluginUrl_Css( $aset ),
							$aDeps,
							$con->getVersion(),
							false
						);
						wp_enqueue_style( $uniq );
						$aDeps[] = $uniq;
					}

					$this->includeScriptIpDetect();
					break;

				case 'notes':
				case 'scans':
				case 'audit':
				case 'traffic':
				case 'ips':
				case 'debug':
				case 'users':

					$aset = 'shield-tables';
					$uniq = $con->prefix( $aset );
					wp_register_script(
						$uniq,
						$con->getPluginUrl_Js( $aset ),
						$aStdDepsJs,
						$con->getVersion(),
						false
					);
					wp_enqueue_script( $uniq );

					$aStdDepsJs[] = $uniq;
					if ( $iNav == 'scans' ) {
						$aset = 'shield-scans';
						$uniq = $con->prefix( $aset );
						wp_register_script(
							$uniq,
							$con->getPluginUrl_Js( $aset ),
							array_unique( $aStdDepsJs ),
							$con->getVersion(),
							false
						);
						wp_enqueue_script( $uniq );
					}

					if ( $iNav == 'ips' ) {
						$aset = 'shield/ipanalyse';
						$uniq = $con->prefix( $aset );
						wp_register_script(
							$uniq,
							$con->getPluginUrl_Js( $aset ),
							array_unique( $aStdDepsJs ),
							$con->getVersion(),
							false
						);
						wp_enqueue_script( $uniq );
					}

					if ( in_array( $iNav, [ 'audit', 'traffic' ] ) ) {
						$uniq = $con->prefix( 'datepicker' );
						wp_register_script(
							$uniq, //TODO: use an includes services for CNDJS
							'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js',
							array_unique( $aStdDepsJs ),
							$con->getVersion(),
							false
						);
						wp_enqueue_script( $uniq );

						wp_register_style(
							$uniq,
							'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css',
							[],
							$con->getVersion(),
							false
						);
						wp_enqueue_style( $uniq );
					}

					break;
			}

			wp_localize_script(
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
			);
		}
	}

	private function includeScriptIpDetect() {
		$con = $this->getCon();
		/** @var Modules\Plugin\Options $opts */
		$opts = $con->getModule_Plugin()->getOptions();
		if ( $opts->isIpSourceAutoDetect() ) {
			wp_register_script(
				$con->prefix( 'ip_detect' ),
				$con->getPluginUrl_Js( 'ip_detect.js' ),
				[],
				$con->getVersion(),
				true
			);
			wp_enqueue_script( $con->prefix( 'ip_detect' ) );

			wp_localize_script(
				$con->prefix( 'ip_detect' ),
				'icwp_wpsf_vars_ipdetect',
				[ 'ajax' => $con->getModule_Plugin()->getAjaxActionData( 'ipdetect' ) ]
			);
		}
	}
}