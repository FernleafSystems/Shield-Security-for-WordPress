<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Insights extends ICWP_WPSF_FeatureHandler_BaseWpsf {

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
		/** @var Shield\Modules\Insights\UI $UI */
		$UI = $this->getUIHandler();
		return $UI->renderPages();
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( $this->isThisModulePage() ) {

			$con = $this->getCon();
			$aStdDepsJs = [ $con->prefix( 'plugin' ) ];
			$iNav = Services::Request()->query( 'inav', 'overview' );

			$oModPlugin = $con->getModule_Plugin();
			$oTourManager = $oModPlugin->getTourManager();
			switch ( $iNav ) {

				case 'importexport':

					$sAsset = 'shield/import';
					$sUnique = $con->prefix( $sAsset );
					wp_register_script(
						$sUnique,
						$con->getPluginUrl_Js( $sAsset ),
						$aStdDepsJs,
						$con->getVersion(),
						false
					);
					wp_enqueue_script( $sUnique );
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
					foreach ( $aJsAssets as $sAsset ) {
						$sUnique = $con->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$con->getPluginUrl_Js( $sAsset ),
							$aDeps,
							$con->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );
						$aDeps[] = $sUnique;
					}

					$aDeps = [];
					$aCssAssets = [ 'chartist.min', 'chartist-plugin-legend' ];
					if ( $oTourManager->canShow( 'insights_overview' ) ) {
						array_unshift( $aCssAssets, 'introjs.min.css' );
					}
					foreach ( $aCssAssets as $sAsset ) {
						$sUnique = $con->prefix( $sAsset );
						wp_register_style(
							$sUnique,
							$con->getPluginUrl_Css( $sAsset ),
							$aDeps,
							$con->getVersion(),
							false
						);
						wp_enqueue_style( $sUnique );
						$aDeps[] = $sUnique;
					}

					$this->includeScriptIpDetect();
					break;

				case 'scans':
				case 'logs':
				case 'ips':
				case 'debug':
				case 'users':

					$sAsset = 'shield-tables';
					$sUnique = $con->prefix( $sAsset );
					wp_register_script(
						$sUnique,
						$con->getPluginUrl_Js( $sAsset ),
						$aStdDepsJs,
						$con->getVersion(),
						false
					);
					wp_enqueue_script( $sUnique );

					$aStdDepsJs[] = $sUnique;
					if ( $iNav == 'scans' ) {
						$sAsset = 'shield-scans';
						$sUnique = $con->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$con->getPluginUrl_Js( $sAsset ),
							array_unique( $aStdDepsJs ),
							$con->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );
					}

					if ( $iNav == 'ips' ) {
						$sAsset = 'shield/ipanalyse';
						$sUnique = $con->prefix( $sAsset );
						wp_register_script(
							$sUnique,
							$con->getPluginUrl_Js( $sAsset ),
							array_unique( $aStdDepsJs ),
							$con->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );
					}

					if ( in_array( $iNav, [ 'logs' ] ) ) {
						$sUnique = $con->prefix( 'datepicker' );
						wp_register_script(
							$sUnique, //TODO: use an includes services for CNDJS
							'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js',
							array_unique( $aStdDepsJs ),
							$con->getVersion(),
							false
						);
						wp_enqueue_script( $sUnique );

						wp_register_style(
							$sUnique,
							'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css',
							[],
							$con->getVersion(),
							false
						);
						wp_enqueue_style( $sUnique );
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
		/** @var Shield\Modules\Plugin\Options $opts */
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

	protected function getNamespaceBase() :string {
		return 'Insights';
	}
}