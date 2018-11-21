<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_HackProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {

		$sPath = $this->loadRequest()->getPath();
		if ( !empty( $sPath ) && ( strpos( $sPath, '/wp-admin/admin-ajax.php' ) !== false ) ) {
			$this->revSliderPatch_LFI();
			$this->revSliderPatch_AFU();
		}
		// not probably necessary any longer since it's patched in the Core
		add_filter( 'pre_comment_content', array( $this, 'secXss64kb' ), 0, 1 );

		$this->runScanner();
	}

	/**
	 */
	protected function runPluginVulnerabilities() {
		require_once( dirname( __FILE__ ).'/hackprotect_pluginvulnerabilities.php' );
		$oPv = new ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities( $this->getMod() );
		$oPv->run();
	}

	/**
	 */
	protected function runScanner() {
		$this->getSubProcessorScanner()->run();
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	public function getSubProcessorScanner() {
		$oProc = $this->getSubPro( 'scanner' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/hackprotect_scanner.php' );
			$oProc = new ICWP_WPSF_Processor_HackProtect_Scanner( $this->getMod() );
			$this->aSubPros[ 'scanner' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * Addresses this vulnerability: http://klikki.fi/adv/wordpress2.html
	 * @param string $sCommentContent
	 * @return string
	 */
	public function secXss64kb( $sCommentContent ) {
		// Comments shouldn't be any longer than 64KB
		if ( strlen( $sCommentContent ) >= ( 64*1024 ) ) {
			$sCommentContent = sprintf( _wpsf__( '%s escaped HTML the following comment due to its size: %s' ), $this->getController()
																													 ->getHumanName(), esc_html( $sCommentContent ) );
		}
		return $sCommentContent;
	}

	protected function revSliderPatch_LFI() {
		$oReq = $this->loadRequest();

		$sAction = $oReq->query( 'action', '' );
		$sFileExt = strtolower( $this->loadDP()->getExtension( $oReq->query( 'img', '' ) ) );
		if ( $sAction == 'revslider_show_image' && !empty( $sFileExt ) ) {
			if ( !in_array( $sFileExt, array( 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'gif' ) ) ) {
				die( 'RevSlider Local File Inclusion Attempt' );
			}
		}
	}

	protected function revSliderPatch_AFU() {
		$oReq = $this->loadRequest();

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
		$oSelector = $oPro->getSubProcessorScanner()->getDbHandler()->getQuerySelector();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aPtgResults */
		$aPtgResults = $oSelector->filterByNotIgnored()
								 ->filterByScan( 'ptg' )
								 ->query();
		$oFullResults = ( new \FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard\ConvertVosToResults() )
			->convert( $aPtgResults );

		// Process Plugins
		$aPlugins = $oFullResults->getAllResultsSetsForPluginsContext();
		$oWpPlugins = \FernleafSystems\Wordpress\Services\Services::WpPlugins();
		foreach ( $aPlugins as $sSlug => $oItemRS ) {
			$aItems = $oItemRS->getAllItems();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard\ResultItem $oIT */
			$oIT = array_pop( $aItems );

			$bInstalled = $oWpPlugins->isInstalled( $oIT->slug );
			$bIsWpOrg = $bInstalled && $oWpPlugins->isWpOrg( $sSlug );
			$bHasUpdate = $bIsWpOrg && $oWpPlugins->isUpdateAvailable( $sSlug );
			$aProfile = array(
				'name'           => _wpsf__( 'unknown' ),
				'version'        => _wpsf__( 'unknown' ),
				'root_dir'       => _wpsf__( 'unknown' ),
				'slug'           => $sSlug,
				'is_wporg'       => $bIsWpOrg,
				'can_reinstall'  => $bIsWpOrg,
				'can_deactivate' => $bInstalled,
				'has_update'     => $bHasUpdate,
				'count_files'    => $oItemRS->countItems(),
				'date_snapshot'  => 'TODODODO',
			);
			if ( $bInstalled ) {
				$oP = $oWpPlugins->getPluginAsVo( $oIT->slug );
				$aProfile[ 'name' ] = $oP->Name;
				$aProfile[ 'version' ] = $oP->Version;
				$aProfile[ 'root_dir' ] = $oWpPlugins->getInstallationDir( $oIT->slug );
			}
			$aProfile[ 'name' ] = sprintf( '%s: %s', __( 'Plugin' ), $aProfile[ 'name' ] );

			$aPlugins[ $sSlug ] = $aProfile;
		}

		// Process Themes
		$aThemes = $oFullResults->getAllResultsSetsForThemesContext();
		$oWpThemes = \FernleafSystems\Wordpress\Services\Services::WpThemes();;
		foreach ( $aThemes as $sSlug => $oItemRS ) {
			$aItems = $oItemRS->getAllItems();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Scans\PTGuard\ResultItem $oIT */
			$oIT = array_pop( $aItems );

			$bInstalled = $oWpThemes->isInstalled( $oIT->slug );
			$bIsWpOrg = $bInstalled && $oWpThemes->isWpOrg( $sSlug );
			$bHasUpdate = $bIsWpOrg && $oWpThemes->isUpdateAvailable( $sSlug );
			$aProfile = array(
				'name'           => _wpsf__( 'unknown' ),
				'version'        => _wpsf__( 'unknown' ),
				'root_dir'       => _wpsf__( 'unknown' ),
				'slug'           => $sSlug,
				'is_wporg'       => $bIsWpOrg,
				'can_reinstall'  => $bIsWpOrg,
				'can_deactivate' => false,
				'has_update'     => $bHasUpdate,
				'count_files'    => $oItemRS->countItems(),
				'date_snapshot'  => 'TODODODO',
			);
			if ( $bInstalled ) {
				$oT = $oWpThemes->getTheme( $oIT->slug );
				$aProfile[ 'name' ] = $oT->get( 'Name' );
				$aProfile[ 'version' ] = $oT->get( 'Version' );
				$aProfile[ 'root_dir' ] = $oWpThemes->getInstallationDir( $oIT->slug );
			}
			$aProfile[ 'name' ] = sprintf( '%s: %s', __( 'Theme' ), $aProfile[ 'name' ] );

			$aThemes[ $sSlug ] = $aProfile;
		}

		$bIsPremium = $oMod->isPremium();
		$oCarbon = new \Carbon\Carbon();
		$aData = array(
			'ajax'    => array(
				'start_scans'       => $oMod->getAjaxActionData( 'start_scans', true ),
				'render_table_scan' => $oMod->getAjaxActionData( 'render_table_scan', true ),
				'item_delete'       => $oMod->getAjaxActionData( 'item_delete', true ),
				'item_ignore'       => $oMod->getAjaxActionData( 'item_ignore', true ),
				'item_repair'       => $oMod->getAjaxActionData( 'item_repair', true ),
			),
			'flags'   => array(
				'is_premium' => $bIsPremium
			),
			'hrefs'   => array(
				'go_pro' => 'https://icwp.io/shieldgoprofeature',
			),
			'strings' => array(
				'never'          => _wpsf__( 'Never' ),
				'go_pro'         => 'Go Pro!',
				'options'        => _wpsf__( 'Scan Options' ),
				'not_available'  => _wpsf__( 'Sorry, this scan is not available.' ),
				'please_upgrade' => _wpsf__( 'Please upgrade to Pro to add this scan and many more features.' ),
			),
			'scans'   => array(
				'wcf' => array(
					'flags'        => array(
						'is_available'  => true,
						'has_last_scan' => $oMod->getLastScanAt( 'wcf' ) > 0
					),
					'hrefs'        => array(
						'options' => $oMod->getUrl_DirectLinkToSection( 'section_core_file_integrity_scan' )
					),
					'vars'         => array(),
					'count'        => $oSelector->countForScan( 'wcf' ),
					'last_scan_at' => sprintf(
						_wpsf__( 'Last Scan: %s' ),
						$oCarbon->setTimestamp( $oMod->getLastScanAt( 'wcf' ) )->diffForHumans()
					),
				),
				'ufc' => array(
					'flags'        => array(
						'is_available'  => true,
						'has_last_scan' => $oMod->getLastScanAt( 'ufc' ) > 0
					),
					'hrefs'        => array(
						'options' => $oMod->getUrl_DirectLinkToSection( 'section_unrecognised_file_scan' )
					),
					'vars'         => array(),
					'count'        => $oSelector->countForScan( 'ufc' ),
					'last_scan_at' => sprintf(
						_wpsf__( 'Last Scan: %s' ),
						$oCarbon->setTimestamp( $oMod->getLastScanAt( 'ufc' ) )->diffForHumans()
					),
				),
				'ptg' => array(
					'flags'        => array(
						'is_available'  => !$bIsPremium,
						'has_last_scan' => $oMod->getLastScanAt( 'ptg' ) > 0,
						'has_items'     => $oFullResults->hasItems(),
						'has_plugins'   => !empty( $aPlugins ),
						'has_themes'    => !empty( $aThemes ),
					),
					'hrefs'        => array(
						'options' => $oMod->getUrl_DirectLinkToSection( 'section_pluginthemes_guard' )
					),
					'vars'         => array(),
					'count'        => $oSelector->countForScan( 'ptg' ),
					'last_scan_at' => sprintf(
						_wpsf__( 'Last Scan: %s' ),
						$oCarbon->setTimestamp( $oMod->getLastScanAt( 'ptg' ) )->diffForHumans()
					),
					'assets'       => array_merge( $aPlugins, $aThemes ),
					'strings'      => array(
						'files_with_problems' => _wpsf__( 'Files with problems' ),
						'root_dir'            => _wpsf__( 'Root directory' ),
						'date_snapshot'       => _wpsf__( 'Snapshot taken' ),
						'reinstall'           => _wpsf__( 'Re-Install' ),
						'deactivate'          => __( 'Deactivate and Ignore' ),
						'accept'              => _wpsf__( 'Accept' ),
						'update'              => _wpsf__( 'Upgrade' ),
					)
				),
			),
		);

		return $aData;
	}
}