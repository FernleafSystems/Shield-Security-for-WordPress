<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Insights extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function onModulesLoaded() {
		$this->maybeRedirectToAdmin();
	}

	private function maybeRedirectToAdmin() {
		$oCon = $this->getCon();
		$nActiveFor = $oCon->getModule_Plugin()->getActivateLength();
		if ( !Services::WpGeneral()->isAjax() && is_admin() && !$oCon->isModulePage() && $nActiveFor < 4 ) {
			Services::Response()->redirect( $this->getUrl_AdminPage() );
		}
	}

	/**
	 * @param string $sSubPage
	 * @return string
	 */
	public function getUrl_SubInsightsPage( $sSubPage ) {
		return add_query_arg(
			[ 'inav' => sanitize_key( $sSubPage ) ],
			$this->getCon()->getModule_Insights()->getUrl_AdminPage()
		);
	}

	/**
	 * @param array $aData
	 * @return string
	 */
	protected function renderModulePage( $aData = [] ) {
		$con = $this->getCon();
		$oReq = Services::Request();

		$sNavSection = $oReq->query( 'inav', 'overview' );
		$sSubNavSection = $oReq->query( 'subnav' );

		$oModPlugin = $con->getModule_Plugin();
		$oTourManager = $oModPlugin->getTourManager();
		if ( !$oTourManager->isCompleted( 'insights_overview' ) && $oModPlugin->getActivateLength() > 600 ) {
			$oTourManager->setCompleted( 'insights_overview' );
		}

		$bIsPro = $this->isPremium();
		switch ( $sNavSection ) {

			case 'audit':
				/** @var Shield\Modules\AuditTrail\UI $UI */
				$UI = $con->getModule_AuditTrail()->getUIHandler();
				$aData = $UI->buildInsightsVars();
				break;

			case 'debug':
				/** @var Shield\Modules\Plugin\UI $UI */
				$UI = $con->getModule_Plugin()->getUIHandler();
				$aData = $UI->buildInsightsVars_Debug();
				break;

			case 'ips':
				/** @var Shield\Modules\IPs\UI $UI */
				$UI = $con->getModule_IPs()->getUIHandler();
				$aData = $UI->buildInsightsVars();
				break;

			case 'notes':
				/** @var Shield\Modules\Plugin\UI $UI */
				$UI = $con->getModule_Plugin()->getUIHandler();
				$aData = $UI->buildInsightsVars_AdminNotes();
				break;

			case 'traffic':
				/** @var Shield\Modules\Traffic\UI $UI */
				$UI = $con->getModule_Traffic()->getUIHandler();
				$aData = $UI->buildInsightsVars();
				break;

			case 'license':
				/** @var Shield\Modules\License\UI $UILicense */
				$UILicense = $con->getModule_License()->getUIHandler();
				$aData = $UILicense->buildInsightsVars();
				break;

			case 'scans':
				/** @var Shield\Modules\HackGuard\UI $UIHackGuard */
				$UIHackGuard = $con->getModule_HackGuard()->getUIHandler();
				$aData = $UIHackGuard->buildInsightsVars();
				break;

			case 'importexport':
				$aData = $oModPlugin->getImpExpController()->buildInsightsVars();
				break;

			case 'reports':
				/** @var Shield\Modules\Reporting\UI $UIReporting */
				$UIReporting = $con->getModule_Reporting()->getUIHandler();
				$aData = $UIReporting->buildInsightsVars();
				break;

			case 'users':
				/** @var Shield\Modules\UserManagement\UI $UIUsers */
				$UIUsers = $con->getModule( 'user_management' )->getUIHandler();
				$aData = $UIUsers->buildInsightsVars();
				break;

			case 'settings':
				$aData = [
					'ajax' => [
						'mod_options'          => $con->getModule( Services::Request()->query( 'subnav' ) )
													  ->getAjaxActionData( 'mod_options', true ),
						'mod_opts_form_render' => $con->getModule( Services::Request()->query( 'subnav' ) )
													  ->getAjaxActionData( 'mod_opts_form_render', true ),
					],
				];
				break;

			case 'overview':
			case 'index':
				/** @var Shield\Modules\Insights\UI $UIInsights */
				$UIInsights = $this->getUIHandler();
				$aData = $UIInsights->buildInsightsVars();
				break;
			default:
				break;
		}

		$aTopNav = [
			'settings'     => __( 'Settings', 'wp-simple-firewall' ),
			'overview'     => __( 'Overview', 'wp-simple-firewall' ),
			'scans'        => __( 'Scans', 'wp-simple-firewall' ),
			'ips'          => __( 'IP Lists', 'wp-simple-firewall' ),
			'audit'        => __( 'Audit Trail', 'wp-simple-firewall' ),
			'users'        => __( 'Users', 'wp-simple-firewall' ),
			'license'      => __( 'Pro', 'wp-simple-firewall' ),
			'traffic'      => __( 'Traffic', 'wp-simple-firewall' ),
			'notes'        => __( 'Notes', 'wp-simple-firewall' ),
			//			'reports'      => __( 'Reports', 'wp-simple-firewall' ),
			'importexport' => sprintf( '%s/%s', __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
			//			'debug'        => __( 'Debug', 'wp-simple-firewall' ),
			'debug'        => __( 'Debug', 'wp-simple-firewall' ),
		];
		if ( $bIsPro ) {
			unset( $aTopNav[ 'license' ] );
			$aTopNav[ 'license' ] = __( 'Pro', 'wp-simple-firewall' );
		}

		array_walk( $aTopNav, function ( &$sName, $sKey ) use ( $sNavSection ) {
			$sName = [
				'href'    => add_query_arg( [ 'inav' => $sKey ], $this->getUrl_AdminPage() ),
				'name'    => $sName,
				'slug'    => $sKey,
				'active'  => $sKey === $sNavSection,
				'subnavs' => []
			];
		} );

		$aSearchSelect = [];
		$aSettingsSubNav = [];
		foreach ( $this->getModulesSummaryData() as $slug => $summary ) {
			if ( $summary[ 'show_mod_opts' ] ) {
				$aSettingsSubNav[ $slug ] = [
					'href'   => add_query_arg( [ 'subnav' => $slug ], $aTopNav[ 'settings' ][ 'href' ] ),
					'name'   => $summary[ 'name' ],
					'active' => $slug === $sSubNavSection,
					'slug'   => $slug
				];

				$aSearchSelect[ $summary[ 'name' ] ] = $summary[ 'options' ];
			}
		}

		if ( empty( $aSettingsSubNav ) ) {
			unset( $aTopNav[ 'settings' ] );
		}
		else {
			$aTopNav[ 'settings' ][ 'subnavs' ] = $aSettingsSubNav;
		}

		$DP = Services::DataManipulation();
		$aData = $DP->mergeArraysRecursive(
			$this->getUIHandler()->getBaseDisplayData(),
			[
				'classes' => [
					'page_container' => 'page-insights page-'.$sNavSection
				],
				'flags'   => [
					'show_promo'       => !$bIsPro && ( $sNavSection != 'settings' ),
					'show_guided_tour' => $oModPlugin->getIfShowIntroVideo(),
					'tours'            => [
						'insights_overview' => $oTourManager->canShow( 'insights_overview' )
					]
				],
				'hrefs'   => [
					'go_pro'     => 'https://shsec.io/shieldgoprofeature',
					'nav_home'   => $this->getUrl_AdminPage(),
					'top_nav'    => $aTopNav,
					'img_banner' => $con->getPluginUrl_Image( 'pluginlogo_banner-170x40.png' )
				],
				'strings' => $this->getStrings()->getDisplayStrings(),
				'vars'    => [
					'changelog_id'  => $con->getPluginSpec()[ 'meta' ][ 'announcekit_changelog_id' ],
					'search_select' => $aSearchSelect
				],
			],
			$aData
		);
		return $this->renderTemplate( sprintf( '/wpadmin_pages/insights/%s/index.twig', $sNavSection ), $aData, true );
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( $this->isThisModulePage() ) {

			$con = $this->getCon();
			$aStdDepsJs = [ $con->prefix( 'plugin' ) ];
			$sNav = Services::Request()->query( 'inav', 'overview' );

			$oModPlugin = $con->getModule_Plugin();
			$oTourManager = $oModPlugin->getTourManager();
			switch ( $sNav ) {

				case 'importexport':

					$sAsset = 'shield-import';
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
				case 'audit':
				case 'ips':
				case 'notes':
				case 'traffic':
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
					if ( $sNav == 'scans' ) {
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

					if ( $sNav == 'audit' ) {
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
		$oCon = $this->getCon();
		/** @var Shield\Modules\Plugin\Options $oOpts */
		$oOpts = $oCon->getModule_Plugin()->getOptions();
		if ( $oOpts->isIpSourceAutoDetect() ) {
			wp_register_script(
				$oCon->prefix( 'ip_detect' ),
				$oCon->getPluginUrl_Js( 'ip_detect.js' ),
				[],
				$oCon->getVersion(),
				true
			);
			wp_enqueue_script( $oCon->prefix( 'ip_detect' ) );

			wp_localize_script(
				$oCon->prefix( 'ip_detect' ),
				'icwp_wpsf_vars_ipdetect',
				[ 'ajax' => $oCon->getModule_Plugin()->getAjaxActionData( 'ipdetect' ) ]
			);
		}
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'Insights';
	}
}