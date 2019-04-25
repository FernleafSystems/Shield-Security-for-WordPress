<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$sPath = Services::Request()->getPath();
		if ( !empty( $sPath ) && ( strpos( $sPath, '/wp-admin/admin-ajax.php' ) !== false ) ) {
			$this->revSliderPatch_LFI();
			$this->revSliderPatch_AFU();
		}
		// not probably necessary any longer since it's patched in the Core
		add_filter( 'pre_comment_content', [ $this, 'secXss64kb' ], 0, 1 );

		$this->getSubProScanner()->run();
		if ( $oMod->isRtEnabledWpConfig() ) {
			$this->getSubProRealtime()->run();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Realtime|mixed
	 */
	public function getSubProRealtime() {
		return $this->getSubPro( 'realtime' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Scanner|mixed
	 */
	public function getSubProScanner() {
		return $this->getSubPro( 'scanner' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'scanner'  => 'ICWP_WPSF_Processor_HackProtect_Scanner',
			'realtime' => 'ICWP_WPSF_Processor_HackProtect_Realtime',
		];
	}

	/**
	 * Addresses this vulnerability: http://klikki.fi/adv/wordpress2.html
	 *
	 * @param string $sCommentContent
	 * @return string
	 */
	public function secXss64kb( $sCommentContent ) {
		// Comments shouldn't be any longer than 64KB
		if ( strlen( $sCommentContent ) >= ( 64*1024 ) ) {
			$sCommentContent = sprintf( _wpsf__( '%s escaped HTML the following comment due to its size: %s' ), $this->getCon()
																													 ->getHumanName(), esc_html( $sCommentContent ) );
		}
		return $sCommentContent;
	}

	protected function revSliderPatch_LFI() {
		$oReq = Services::Request();

		$sAction = $oReq->query( 'action', '' );
		$sFileExt = strtolower( Services::Data()->getExtension( $oReq->query( 'img', '' ) ) );
		if ( $sAction == 'revslider_show_image' && !empty( $sFileExt ) ) {
			if ( !in_array( $sFileExt, [ 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'gif' ] ) ) {
				die( 'RevSlider Local File Inclusion Attempt' );
			}
		}
	}

	protected function revSliderPatch_AFU() {
		$oReq = Services::Request();

		$sAction = strtolower( $oReq->request( 'action', '' ) );
		$sClientAction = strtolower( $oReq->request( 'client_action', '' ) );
		if ( ( strpos( $sAction, 'revslider_ajax_action' ) !== false || strpos( $sAction, 'showbiz_ajax_action' ) !== false ) && $sClientAction == 'update_plugin' ) {
			die( 'RevSlider Arbitrary File Upload Attempt' );
		}
	}

	/**
	 * @return array
	 */
	public function buildInsightsVars() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		/** @var ICWP_WPSF_Processor_HackProtect $oPro */
		$oPro = $oMod->getProcessor();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $oPro->getSubProScanner()->getDbHandler()->getQuerySelector();

		$oCarbon = new \Carbon\Carbon();
		$aData = [
			'ajax'    => [
				'start_scans'           => $oMod->getAjaxActionData( 'start_scans', true ),
				'render_table_scan'     => $oMod->getAjaxActionData( 'render_table_scan', true ),
				'bulk_action'           => $oMod->getAjaxActionData( 'bulk_action', true ),
				'item_asset_accept'     => $oMod->getAjaxActionData( 'item_asset_accept', true ),
				'item_asset_deactivate' => $oMod->getAjaxActionData( 'item_asset_deactivate', true ),
				'item_asset_reinstall'  => $oMod->getAjaxActionData( 'item_asset_reinstall', true ),
				'item_delete'           => $oMod->getAjaxActionData( 'item_delete', true ),
				'item_ignore'           => $oMod->getAjaxActionData( 'item_ignore', true ),
				'item_repair'           => $oMod->getAjaxActionData( 'item_repair', true ),
			],
			'flags'   => [
				'is_premium' => $oMod->isPremium()
			],
			'strings' => [
				'never'         => _wpsf__( 'Never' ),
				'go_pro'        => 'Go Pro!',
				'options'       => _wpsf__( 'Scan Options' ),
				'not_available' => _wpsf__( 'Sorry, this scan is not available.' ),
				'not_enabled'   => _wpsf__( 'This scan is not currently enabled.' ),
				'please_enable' => _wpsf__( 'Please turn on this scan in the options.' ),
			],
			'vars'    => [
			],
			'scans'   => [
				'apc' => [
					'flags'   => [
						'is_enabled'    => true,
						'is_available'  => true,
						'has_items'     => true,
						'has_last_scan' => $oMod->getLastScanAt( 'apc' ) > 0
					],
					'hrefs'   => [
						'options' => $oMod->getUrl_DirectLinkToSection( 'section_scan_apc' )
					],
					'vars'    => [
						'last_scan_at' => sprintf(
							_wpsf__( 'Last Scan: %s' ),
							$oCarbon->setTimestamp( $oMod->getLastScanAt( 'apc' ) )->diffForHumans()
						),
					],
					'count'   => $oSelector->countForScan( 'apc' ),
					'strings' => [
						'title'    => _wpsf__( 'Abandoned Plugins Check' ),
						'subtitle' => _wpsf__( "Discover abandoned plugins" )
					],
				],
				'wcf' => [
					'flags'   => [
						'is_enabled'    => true,
						'is_available'  => true,
						'has_items'     => true,
						'has_last_scan' => $oMod->getLastScanAt( 'wcf' ) > 0
					],
					'hrefs'   => [
						'options' => $oMod->getUrl_DirectLinkToSection( 'section_core_file_integrity_scan' )
					],
					'vars'    => [
						'last_scan_at' => sprintf(
							_wpsf__( 'Last Scan: %s' ),
							$oCarbon->setTimestamp( $oMod->getLastScanAt( 'wcf' ) )->diffForHumans()
						),
					],
					'count'   => $oSelector->countForScan( 'wcf' ),
					'strings' => [
						'title'    => _wpsf__( 'WordPress Core File Integrity' ),
						'subtitle' => _wpsf__( "Detects changes to core WordPress files" )
					],
				],
				'ufc' => [
					'flags'   => [
						'is_enabled'    => true,
						'is_available'  => true,
						'has_items'     => true,
						'has_last_scan' => $oMod->getLastScanAt( 'ufc' ) > 0
					],
					'hrefs'   => [
						'options' => $oMod->getUrl_DirectLinkToSection( 'section_unrecognised_file_scan' )
					],
					'vars'    => [
						'last_scan_at' => sprintf(
							_wpsf__( 'Last Scan: %s' ),
							$oCarbon->setTimestamp( $oMod->getLastScanAt( 'ufc' ) )->diffForHumans()
						),
					],
					'count'   => $oSelector->countForScan( 'ufc' ),
					'strings' => [
						'title'    => _wpsf__( 'Unrecognised Core Files' ),
						'subtitle' => _wpsf__( "Detects files that maybe shouldn't be there" )
					],
				],
				'wpv' => [
					'flags'   => [
						'is_enabled'    => $oMod->isWpvulnEnabled(),
						'is_available'  => $oMod->isPremium(),
						'has_items'     => true,
						'has_last_scan' => $oMod->getLastScanAt( 'wpv' ) > 0
					],
					'hrefs'   => [
						'options' => $oMod->getUrl_DirectLinkToSection( 'section_wpvuln_scan' )
					],
					'vars'    => [
						'last_scan_at' => sprintf(
							_wpsf__( 'Last Scan: %s' ),
							$oCarbon->setTimestamp( $oMod->getLastScanAt( 'wpv' ) )->diffForHumans()
						),
					],
					'count'   => $oSelector->countForScan( 'wpv' ),
					'strings' => [
						'title'    => _wpsf__( 'Plugin / Theme Vulnerabilities' ),
						'subtitle' => _wpsf__( "Alerts on known security vulnerabilities" )
					],
				],
				'ptg' => $this->getInsightVarsScan_Ptg(),
			],
		];

		return $aData;
	}

	/**
	 * @return array
	 */
	private function getInsightVarsScan_Ptg() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oCon = $this->getCon();
		$oCarbon = new \Carbon\Carbon();

		/** @var ICWP_WPSF_Processor_HackProtect $oPro */
		$oPro = $oMod->getProcessor();
		$oProPtg = $oPro->getSubProScanner()->getSubProcessorPtg();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $oPro->getSubProScanner()->getDbHandler()->getQuerySelector();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aPtgResults */
		$aPtgResults = $oSelector->filterByNotIgnored()
								 ->filterByScan( 'ptg' )
								 ->query();
		$oFullResults = ( new \FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ConvertVosToResults() )
			->convert( $aPtgResults );

		// Process Plugins
		$aPlugins = $oFullResults->getAllResultsSetsForPluginsContext();
		$oWpPlugins = Services::WpPlugins();
		foreach ( $aPlugins as $sSlug => $oItemRS ) {
			$aItems = $oItemRS->getAllItems();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem $oIT */
			$oIT = array_pop( $aItems );
			$aMeta = $oProPtg->getSnapshotItemMeta( $oIT->slug );
			if ( !empty( $aMeta[ 'ts' ] ) ) {
				$aMeta[ 'ts' ] = $oCarbon->setTimestamp( $aMeta[ 'ts' ] )->diffForHumans();
			}
			else {
				$aMeta[ 'ts' ] = _wpsf__( 'unknown' );
			}

			$bInstalled = $oWpPlugins->isInstalled( $oIT->slug );
			$bIsWpOrg = $bInstalled && $oWpPlugins->isWpOrg( $sSlug );
			$bHasUpdate = $bIsWpOrg && $oWpPlugins->isUpdateAvailable( $sSlug );
			$aProfile = [
				'id'             => $oSelector->filterByHash( $oIT->hash )->first()->id,
				'name'           => _wpsf__( 'unknown' ),
				'version'        => _wpsf__( 'unknown' ),
				'root_dir'       => $oWpPlugins->getInstallationDir( $oIT->slug ),
				'slug'           => $sSlug,
				'is_wporg'       => $bIsWpOrg,
				'can_reinstall'  => $bIsWpOrg,
				'can_deactivate' => $bInstalled && ( $sSlug !== $oCon->getPluginBaseFile() ),
				'has_update'     => $bHasUpdate,
				'count_files'    => $oItemRS->countItems(),
				'date_snapshot'  => $aMeta[ 'ts' ],
			];

			if ( $bInstalled ) {
				$oP = $oWpPlugins->getPluginAsVo( $oIT->slug );
				$aProfile[ 'name' ] = $oP->Name;
				$aProfile[ 'version' ] = $oP->Version;
			}
			else {
				// MISSING!
				if ( is_array( $aMeta ) ) {
					$aProfile[ 'name' ] = isset( $aMeta[ 'name' ] ) ? $aMeta[ 'name' ] : _wpsf__( 'unknown' );
					$aProfile[ 'version' ] = isset( $aMeta[ 'version' ] ) ? $aMeta[ 'version' ] : _wpsf__( 'unknown' );
				}
			}
			$aProfile[ 'name' ] = sprintf( '%s: %s', __( 'Plugin' ), $aProfile[ 'name' ] );

			$aPlugins[ $sSlug ] = $aProfile;
		}

		// Process Themes
		$aThemes = $oFullResults->getAllResultsSetsForThemesContext();
		$oWpThemes = Services::WpThemes();;
		foreach ( $aThemes as $sSlug => $oItemRS ) {
			$aItems = $oItemRS->getAllItems();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem $oIT */
			$oIT = array_pop( $aItems );
			$aMeta = $oProPtg->getSnapshotItemMeta( $oIT->slug );
			if ( !empty( $aMeta[ 'ts' ] ) ) {
				$aMeta[ 'ts' ] = $oCarbon->setTimestamp( $aMeta[ 'ts' ] )->diffForHumans();
			}
			else {
				$aMeta[ 'ts' ] = _wpsf__( 'unknown' );
			}

			$bInstalled = $oWpThemes->isInstalled( $oIT->slug );
			$bIsWpOrg = $bInstalled && $oWpThemes->isWpOrg( $sSlug );
			$bHasUpdate = $bIsWpOrg && $oWpThemes->isUpdateAvailable( $sSlug );
			$aProfile = [
				'id'             => $oSelector->filterByHash( $oIT->hash )->first()->id,
				'name'           => _wpsf__( 'unknown' ),
				'version'        => _wpsf__( 'unknown' ),
				'root_dir'       => _wpsf__( 'unknown' ),
				'slug'           => $sSlug,
				'is_wporg'       => $bIsWpOrg,
				'can_reinstall'  => $bIsWpOrg,
				'can_deactivate' => false,
				'has_update'     => $bHasUpdate,
				'count_files'    => $oItemRS->countItems(),
				'date_snapshot'  => $aMeta[ 'ts' ],
			];
			if ( $bInstalled ) {
				$oT = $oWpThemes->getTheme( $oIT->slug );
				$aProfile[ 'name' ] = $oT->get( 'Name' );
				$aProfile[ 'version' ] = $oT->get( 'Version' );
				$aProfile[ 'root_dir' ] = $oWpThemes->getInstallationDir( $oIT->slug );
			}
			$aProfile[ 'name' ] = sprintf( '%s: %s', __( 'Theme' ), $aProfile[ 'name' ] );

			$aThemes[ $sSlug ] = $aProfile;
		}

		return [
			'flags'   => [
				'is_enabled'    => $oMod->isPtgEnabled(),
				'is_available'  => $oMod->isPremium(),
				'has_last_scan' => $oMod->getLastScanAt( 'ptg' ) > 0,
				'has_items'     => $oFullResults->hasItems(),
				'has_plugins'   => !empty( $aPlugins ),
				'has_themes'    => !empty( $aThemes ),
			],
			'hrefs'   => [
				'options'       => $oMod->getUrl_DirectLinkToSection( 'section_pluginthemes_guard' ),
				'please_enable' => $oMod->getUrl_DirectLinkToSection( 'section_pluginthemes_guard' ),
			],
			'vars'    => [
				'last_scan_at' => sprintf(
					_wpsf__( 'Last Scan: %s' ),
					$oCarbon->setTimestamp( $oMod->getLastScanAt( 'ptg' ) )->diffForHumans()
				)
			],
			'count'   => $oSelector->countForScan( 'ptg' ),
			'assets'  => array_merge( $aPlugins, $aThemes ),
			'strings' => [
				'title'               => _wpsf__( 'Plugin / Theme Modifications' ),
				'subtitle'            => _wpsf__( "Detects unauthorized changes to plugins/themes" ),
				'files_with_problems' => _wpsf__( 'Files with problems' ),
				'root_dir'            => _wpsf__( 'Root directory' ),
				'date_snapshot'       => _wpsf__( 'Snapshot taken' ),
				'reinstall'           => _wpsf__( 'Re-Install' ),
				'deactivate'          => __( 'Deactivate and Ignore' ),
				'accept'              => _wpsf__( 'Accept' ),
				'update'              => _wpsf__( 'Upgrade' ),
			]
		];
	}
}