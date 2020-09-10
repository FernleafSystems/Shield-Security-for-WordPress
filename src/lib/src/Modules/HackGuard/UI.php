<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	public function getInsightsOverviewCards() :array {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$aScanNames = $mod->getStrings()->getScanNames();

		$cardSection = [
			'title'        => __( 'Hack Guard', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Threats/Intrusions Detection & Repair', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$bGoodFrequency = $opts->getScanFrequency() > 1;
			$cards[ 'frequency' ] = [
				'name'    => __( 'Scan Frequency', 'wp-simple-firewall' ),
				'state'   => $bGoodFrequency ? 1 : 0,
				'summary' => $bGoodFrequency ?
					__( 'Automatic scanners run more than once per day', 'wp-simple-firewall' )
					: __( "Automatic scanners only run once per day", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_options' ),
			];

			$bCore = $mod->getScanCon( 'wcf' )->isEnabled();
			$cards[ 'wcf' ] = [
				'name'    => __( 'WP Core File Scan', 'wp-simple-firewall' ),
				'state'   => $bCore ? 1 : -2,
				'summary' => $bCore ?
					__( 'Core files scanned regularly for hacks', 'wp-simple-firewall' )
					: __( "Core files are never scanned for hacks!", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
			];
			if ( $bCore && !$opts->isRepairFileWP() ) {
				$cards[ 'wcf_repair' ] = [
					'name'    => __( 'WP Core File Repair', 'wp-simple-firewall' ),
					'state'   => $opts->isRepairFileWP() ? 1 : 0,
					'summary' => $opts->isRepairFileWP() ?
						__( 'Core files are automatically repaired', 'wp-simple-firewall' )
						: __( "Core files aren't automatically repaired!", 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'file_repair_areas' ),
				];
			}

			$bUcf = $mod->getScanCon( 'ufc' )->isEnabled();
			$cards[ 'ufc' ] = [
				'name'    => __( 'Unrecognised Files', 'wp-simple-firewall' ),
				'state'   => $bUcf ? 1 : -1,
				'summary' => $bUcf ?
					__( 'Core directories scanned regularly for unrecognised files', 'wp-simple-firewall' )
					: __( "WP Core is never scanned for unrecognised files!", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
			];
			if ( $bUcf && !$opts->isUfsDeleteFiles() ) {
				$cards[ 'ufc_repair' ] = [
					'name'    => __( 'Unrecognised Files Removal', 'wp-simple-firewall' ),
					'state'   => $opts->isUfsDeleteFiles() ? 1 : 0,
					'summary' => $opts->isUfsDeleteFiles() ?
						__( 'Unrecognised files are automatically removed', 'wp-simple-firewall' )
						: __( "Unrecognised files aren't automatically removed!", 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
				];
			}

			$bWpv = $mod->getScanCon( 'wpv' )->isEnabled();
			$cards[ 'wpv' ] = [
				'name'    => __( 'Vulnerability Scan', 'wp-simple-firewall' ),
				'state'   => $bWpv ? 1 : -1,
				'summary' => $bWpv ?
					__( 'Regularly scanning for known vulnerabilities', 'wp-simple-firewall' )
					: __( "Plugins/Themes never scanned for vulnerabilities!", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
			];
			$bWpvAutoUpdates = $opts->isWpvulnAutoupdatesEnabled();
			if ( $bWpv && !$bWpvAutoUpdates ) {
				$cards[ 'wpv_repair' ] = [
					'name'    => __( 'Auto Update', 'wp-simple-firewall' ),
					'state'   => $bWpvAutoUpdates ? 1 : 0,
					'summary' => $bWpvAutoUpdates ?
						__( 'Vulnerable items are automatically updated', 'wp-simple-firewall' )
						: __( "Vulnerable items aren't automatically updated!", 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
				];
			}

			$bPtg = $mod->getScanCon( 'ptg' )->isEnabled();
			$cards[ 'ptg' ] = [
				'title'   => $aScanNames[ 'ptg' ],
				'name'    => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'state'   => $bPtg ? 1 : -1,
				'summary' => $bPtg ?
					__( 'Plugins and Themes are guarded against tampering', 'wp-simple-firewall' )
					: __( "Plugins and Themes are never scanned for tampering!", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'ptg_enable' ),
			];

			$bMal = $mod->getScanCon( 'mal' )->isEnabled();
			$cards[ 'mal' ] = [
				'title'   => $aScanNames[ 'mal' ],
				'name'    => $aScanNames[ 'mal' ],
				'state'   => $bMal ? 1 : -1,
				'summary' => $bMal ?
					sprintf( __( '%s Scanner is enabled.' ), $aScanNames[ 'mal' ] )
					: sprintf( __( '%s Scanner is not enabled.' ), $aScanNames[ 'mal' ] ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_mal' ),
			];

			$bApc = $mod->getScanCon( 'apc' )->isEnabled();
			$cards[ 'apc' ] = [
				'title'   => $aScanNames[ 'apc' ],
				'name'    => $aScanNames[ 'apc' ],
				'state'   => $bApc ? 1 : -1,
				'summary' => $bApc ?
					sprintf( __( '%s Scanner is enabled.' ), $aScanNames[ 'apc' ] )
					: sprintf( __( '%s Scanner is not enabled.' ), $aScanNames[ 'apc' ] ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_apc' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'hack_protect' => $cardSection ];
	}

	/**
	 * @return array
	 */
	public function buildInsightsVars() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aLatestScans = array_map(
			function ( $nTime ) {
				return sprintf(
					__( 'Last Scan: %s', 'wp-simple-firewall' ),
					( $nTime > 0 ) ?
						Services::Request()->carbon()->setTimestamp( $nTime )->diffForHumans()
						: __( 'Never', 'wp-simple-firewall' )
				);
			},
			$mod->getLastScansAt()
		);

		$aUiTrack = $mod->getUiTrack();
		if ( empty( $aUiTrack[ 'selected_scans' ] ) ) {
			$aUiTrack[ 'selected_scans' ] = $opts->getScanSlugs();
		}

		// Can Scan Checks:
		$aReasonCantScan = $mod->getProcessor()
							   ->getSubProScanner()
							   ->getReasonsScansCantExecute();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $mod->getDbHandler_ScanResults()->getQuerySelector();
		$aData = [
			'ajax'         => [
				'scans_start'           => $mod->getAjaxActionData( 'scans_start', true ),
				'scans_check'           => $mod->getAjaxActionData( 'scans_check', true ),
				'render_table_scan'     => $mod->getAjaxActionData( 'render_table_scan', true ),
				'bulk_action'           => $mod->getAjaxActionData( 'bulk_action', true ),
				'item_asset_deactivate' => $mod->getAjaxActionData( 'item_asset_deactivate', true ),
				'item_asset_reinstall'  => $mod->getAjaxActionData( 'item_asset_reinstall', true ),
				'item_delete'           => $mod->getAjaxActionData( 'item_delete', true ),
				'item_ignore'           => $mod->getAjaxActionData( 'item_ignore', true ),
				'item_repair'           => $mod->getAjaxActionData( 'item_repair', true ),
				'item_action'           => $mod->getAjaxActionData( 'item_action', true ),
			],
			'flags'        => [
				'is_premium' => $this->getCon()->isPremiumActive(),
				'can_scan'   => count( $aReasonCantScan ) === 0,
			],
			'strings'      => [
				'never'                 => __( 'Never', 'wp-simple-firewall' ),
				'not_available'         => __( 'Sorry, this scan is not available.', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
				'click_see_results'     => __( 'Click a scan to see its results', 'wp-simple-firewall' ),
				'title_scan_site_now'   => __( 'Scan Your Site Now', 'wp-simple-firewall' ),
				'title_scan_now'        => __( 'Scan Your Site Now', 'wp-simple-firewall' ),
				'subtitle_scan_now'     => __( 'Run the selected scans on your site now to get the latest results', 'wp-simple-firewall' ),
				'more_items_longer'     => __( 'The more scans that are selected, the longer the scan may take.', 'wp-simple-firewall' ),
				'scan_options'          => __( 'Scan Options', 'wp-simple-firewall' ),
				'scanselect'            => __( 'Select Scans To Run', 'wp-simple-firewall' ),
				'scanselect_file_areas' => __( 'Select File Scans To Run', 'wp-simple-firewall' ),
				'scanselect_assets'     => __( 'Select Scans For Plugins and Themes', 'wp-simple-firewall' ),
				'select_view_results'   => __( 'View Scan Results', 'wp-simple-firewall' ),
				'select_what_to_scan'   => __( 'Select Scans To Run', 'wp-simple-firewall' ),
				'clear_ignore'          => __( 'Clear Ignore Flags', 'wp-simple-firewall' ),
				'clear_ignore_sub'      => __( 'Previously ignored results will be revealed (for the selected scans only)', 'wp-simple-firewall' ),
				'clear_suppression'     => __( 'Remove Notification Suppression', 'wp-simple-firewall' ),
				'clear_suppression_sub' => __( 'Allow notification emails to be resent (for the selected scans only)', 'wp-simple-firewall' ),
				'run_scans_now'         => __( 'Run Scans Now', 'wp-simple-firewall' ),
				'no_entries_to_display' => __( "The previous scan either didn't detect any items that require your attention or they've already been repaired.", 'wp-simple-firewall' ),
				'scan_progress'         => __( 'Scan Progress', 'wp-simple-firewall' ),
				'reason_not_call_self'  => __( "This site currently can't make HTTP requests to itself.", 'wp-simple-firewall' ),
			],
			'vars'         => [
				'initial_check'       => $mod->getScanQueueController()->hasRunningScans(),
				'cannot_scan_reasons' => $aReasonCantScan
			],
			'scan_results' => [
			],
			'aggregate'    => [
				'flags'   => [
					'has_items' => true,
				],
				'hrefs'   => [
					'options' => $mod->getUrl_DirectLinkToSection( 'section_scan_options' )
				],
				'vars'    => [
				],
				'strings' => [
					'title'    => __( 'File Scan', 'wp-simple-firewall' ),
					'subtitle' => __( "Results of all file scans", 'wp-simple-firewall' )
				],
				'count'   => $oSelector->filterByScans( [ 'ptg', 'mal', 'wcf', 'ufc' ] )
									   ->filterByNotIgnored()
									   ->count()
			],
			'file_locker'  => $this->getFileLockerVars(),
			'scans'        => [
				'apc' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => true,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Discover plugins that may have been abandoned by their authors", 'wp-simple-firewall' )
					],
				],
				'wcf' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => false,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Detect changes to core WordPress files when compared to the official distribution", 'wp-simple-firewall' ),
					],
				],
				'ufc' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => false,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Detect files which aren't part of the official WordPress.org distribution", 'wp-simple-firewall' )
					],
				],
				'mal' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => false,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Detect files that may be infected with malware", 'wp-simple-firewall' )
					],
				],
				'ptg' => $this->getInsightVarsScan_Ptg(),
				'wpv' => [
					'flags'   => [
						'has_items'  => true,
						'show_table' => true,
					],
					'hrefs'   => [],
					'vars'    => [],
					'strings' => [
						'subtitle' => __( "Be alerted to plugins and themes with known security vulnerabilities", 'wp-simple-firewall' )
					],
				],
			],
		];

		/** @var Strings $oStrings */
		$oStrings = $mod->getStrings();
		$aScanNames = $oStrings->getScanNames();
		foreach ( $aData[ 'scans' ] as $sSlug => &$aScanData ) {
			$oScanCon = $mod->getScanCon( $sSlug );
			$aScanData[ 'flags' ][ 'is_available' ] = $oScanCon->isScanningAvailable();
			$aScanData[ 'flags' ][ 'is_restricted' ] = !$oScanCon->isScanningAvailable();
			$aScanData[ 'flags' ][ 'is_enabled' ] = $oScanCon->isEnabled();
			$aScanData[ 'flags' ][ 'is_selected' ] = $oScanCon->isScanningAvailable() && in_array( $sSlug, $aUiTrack[ 'selected_scans' ] );
			$aScanData[ 'flags' ][ 'has_last_scan' ] = $mod->getLastScanAt( $sSlug ) > 0;
			$aScanData[ 'vars' ][ 'last_scan_at' ] = $aLatestScans[ $sSlug ];
			$aScanData[ 'strings' ][ 'title' ] = $aScanNames[ $sSlug ];
			$aScanData[ 'hrefs' ][ 'options' ] = $mod->getUrl_DirectLinkToSection( 'section_scan_'.$sSlug );
			$aScanData[ 'hrefs' ][ 'please_enable' ] = $mod->getUrl_DirectLinkToSection( 'section_scan_'.$sSlug );
			$aScanData[ 'count' ] = $oSelector->countForScan( $sSlug );
		}

		return $aData;
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 */
	protected function buildOptionForUi( $aOptParams ) {
		$aOptParams = parent::buildOptionForUi( $aOptParams );
		if ( $aOptParams[ 'key' ] === 'file_locker' && !Services::Data()->isWindows() ) {
			$aOptParams[ 'value_options' ][ 'root_webconfig' ] .= sprintf( ' (%s)', __( 'unavailable', 'wp-simple-firewall' ) );
		}
		return $aOptParams;
	}

	/**
	 * @return array
	 */
	protected function getFileLockerVars() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $mod */
		$mod = $this->getMod();

		$oLockCon = $mod->getFileLocker();
		$oLockLoader = ( new LoadFileLocks() )->setMod( $mod );
		$aProblemLocks = $oLockLoader->withProblems();
		$aGoodLocks = $oLockLoader->withoutProblems();

		return [
			'ajax'    => [
				'filelocker_showdiff'   => $mod->getAjaxActionData( 'filelocker_showdiff', true ),
				'filelocker_fileaction' => $mod->getAjaxActionData( 'filelocker_fileaction', true ),
			],
			'flags'   => [
				'is_enabled'    => $oLockCon->isEnabled(),
				'is_restricted' => !$this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'options'       => $mod->getUrl_DirectLinkToSection( 'section_realtime' ),
				'please_enable' => $mod->getUrl_DirectLinkToSection( 'section_realtime' ),
			],
			'vars'    => [
				'file_locks' => [
					'good' => $aGoodLocks,
					'bad'  => $aProblemLocks,
				],
			],
			'strings' => [
				'title'         => __( 'File Locker', 'wp-simple-firewall' ),
				'subtitle'      => __( 'Results of file locker monitoring', 'wp-simple-firewall' ),
				'please_select' => __( 'Please select a file to review.', 'wp-simple-firewall' ),
			],
			'count'   => count( $aProblemLocks )
		];
	}

	/**
	 * @return array
	 */
	private function getInsightVarsScan_Ptg() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oReq = Services::Request();

		/** @var \ICWP_WPSF_Processor_HackProtect $oPro */
		$oPro = $oMod->getProcessor();
		$oProPtg = $oPro->getSubProScanner()->getSubProcessorPtg();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $oMod->getDbHandler_ScanResults()->getQuerySelector();

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aPtgResults */
		$aPtgResults = $oSelector->filterByNotIgnored()
								 ->filterByScan( 'ptg' )
								 ->query();
		/** @var Shield\Scans\Ptg\ResultsSet $oFullResults */
		$oFullResults = ( new Shield\Modules\HackGuard\Scan\Results\ConvertBetweenTypes() )
			->setScanController( $oMod->getScanCon( 'ptg' ) )
			->fromVOsToResultsSet( $aPtgResults );

		// Process Plugins
		$aPlugins = $oFullResults->getAllResultsSetsForPluginsContext();
		$oWpPlugins = Services::WpPlugins();
		foreach ( $aPlugins as $sSlug => $oItemRS ) {
			$aItems = $oItemRS->getAllItems();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem $oIT */
			$oIT = array_pop( $aItems );
			$aMeta = $oProPtg->getSnapshotItemMeta( $oIT->slug );
			if ( !empty( $aMeta[ 'ts' ] ) ) {
				$aMeta[ 'ts' ] = $oReq->carbon()->setTimestamp( $aMeta[ 'ts' ] )->diffForHumans();
			}
			else {
				$aMeta[ 'ts' ] = __( 'unknown', 'wp-simple-firewall' );
			}

			$bInstalled = $oWpPlugins->isInstalled( $oIT->slug );
			$oPlgn = $oWpPlugins->getPluginAsVo( $oIT->slug );
			$bIsWpOrg = $bInstalled && $oPlgn instanceof WpPluginVo && $oPlgn->isWpOrg();
			$bHasUpdate = $bIsWpOrg && $oPlgn->hasUpdate();
			$aProfile = [
				'id'             => $oSelector->filterByHash( $oIT->hash )->first()->id,
				'name'           => __( 'unknown', 'wp-simple-firewall' ),
				'version'        => __( 'unknown', 'wp-simple-firewall' ),
				'root_dir'       => $oWpPlugins->getInstallationDir( $oIT->slug ),
				'slug'           => $sSlug,
				'is_wporg'       => $bIsWpOrg,
				'can_reinstall'  => $bIsWpOrg,
				'can_deactivate' => $bInstalled && ( $sSlug !== $this->getCon()->getPluginBaseFile() ),
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
					$aProfile[ 'name' ] = isset( $aMeta[ 'name' ] ) ? $aMeta[ 'name' ] : __( 'unknown', 'wp-simple-firewall' );
					$aProfile[ 'version' ] = isset( $aMeta[ 'version' ] ) ? $aMeta[ 'version' ] : __( 'unknown', 'wp-simple-firewall' );
				}
			}
			$aProfile[ 'name' ] = sprintf( '%s: %s', __( 'Plugin' ), $aProfile[ 'name' ] );

			$aPlugins[ $sSlug ] = $aProfile;
		}

		// Process Themes
		$aThemes = $oFullResults->getAllResultsSetsForThemesContext();
		$oWpThemes = Services::WpThemes();
		foreach ( $aThemes as $sSlug => $oItemRS ) {
			$aItems = $oItemRS->getAllItems();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem $oIT */
			$oIT = array_pop( $aItems );
			$aMeta = $oProPtg->getSnapshotItemMeta( $oIT->slug );
			if ( !empty( $aMeta[ 'ts' ] ) ) {
				$aMeta[ 'ts' ] = $oReq->carbon()->setTimestamp( $aMeta[ 'ts' ] )->diffForHumans();
			}
			else {
				$aMeta[ 'ts' ] = __( 'unknown', 'wp-simple-firewall' );
			}

			$bInstalled = $oWpThemes->isInstalled( $oIT->slug );
			$bIsWpOrg = $bInstalled && $oWpThemes->isWpOrg( $sSlug );
			$bHasUpdate = $bIsWpOrg && $oWpThemes->isUpdateAvailable( $sSlug );
			$aProfile = [
				'id'             => $oSelector->filterByHash( $oIT->hash )->first()->id,
				'name'           => __( 'unknown', 'wp-simple-firewall' ),
				'version'        => __( 'unknown', 'wp-simple-firewall' ),
				'root_dir'       => __( 'unknown', 'wp-simple-firewall' ),
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
				'has_items'   => $oMod->isPtgEnabled() ? $oFullResults->hasItems() : false,
				'has_plugins' => !empty( $aPlugins ),
				'has_themes'  => !empty( $aThemes ),
				'show_table'  => false,
			],
			'hrefs'   => [],
			'vars'    => [],
			'assets'  => $oMod->isPtgEnabled() ? array_merge( $aPlugins, $aThemes ) : [],
			'strings' => [
				'subtitle'            => __( "Detects unauthorized changes to plugins/themes", 'wp-simple-firewall' ),
				'files_with_problems' => __( 'Files with problems', 'wp-simple-firewall' ),
				'root_dir'            => __( 'Root directory', 'wp-simple-firewall' ),
				'date_snapshot'       => __( 'Snapshot taken', 'wp-simple-firewall' ),
				'reinstall'           => __( 'Re-Install', 'wp-simple-firewall' ),
				'deactivate'          => __( 'Deactivate and Ignore', 'wp-simple-firewall' ),
				'accept'              => __( 'Accept', 'wp-simple-firewall' ),
				'update'              => __( 'Upgrade', 'wp-simple-firewall' ),
			]
		];
	}

	/**
	 * @return array
	 */
	public function getInsightsNoticesData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $mod */
		$mod = $this->getMod();
		/** @var Strings $oStrings */
		$oStrings = $mod->getStrings();
		$aScanNames = $oStrings->getScanNames();

		$notices = [
			'title'    => __( 'Scans', 'wp-simple-firewall' ),
			'messages' => []
		];

		$sScansUrl = $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' );

		{// Malware
			$scan = $mod->getScanCon( 'mal' );
			if ( !$scan->isEnabled() ) {
				$notices[ 'messages' ][ 'mal' ] = [
					'title'   => $aScanNames[ 'mal' ],
					'message' => sprintf( __( '%s Scanner is not enabled.' ), $aScanNames[ 'mal' ] ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_mal' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of Malware is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $scan->getScanHasProblem() ) {
				$notices[ 'messages' ][ 'mal' ] = [
					'title'   => $aScanNames[ 'mal' ],
					'message' => __( 'At least 1 file with potential Malware has been discovered.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Files identified as potential malware should be examined as soon as possible.', 'wp-simple-firewall' )
				];
			}
		}

		{// Core files
			$scan = $mod->getScanCon( 'wcf' );
			if ( !$scan->isEnabled() ) {
				$notices[ 'messages' ][ 'wcf' ] = [
					'title'   => $aScanNames[ 'wcf' ],
					'message' => __( 'Core File scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic WordPress Core File scanner should be turned-on.', 'wp-simple-firewall' )
				];
			}
			elseif ( $scan->getScanHasProblem() ) {
				$notices[ 'messages' ][ 'wcf' ] = [
					'title'   => $aScanNames[ 'wcf' ],
					'message' => __( 'Modified WordPress core files found.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Scan WP core files and repair any files that are flagged as modified.', 'wp-simple-firewall' )
				];
			}
		}

		{// Unrecognised
			$scan = $mod->getScanCon( 'ufc' );
			if ( !$scan->isEnabled() ) {
				$notices[ 'messages' ][ 'ufc' ] = [
					'title'   => $aScanNames[ 'ufc' ],
					'message' => __( 'Unrecognised File scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic scanning for non-WordPress core files is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $scan->getScanHasProblem() ) {
				$notices[ 'messages' ][ 'ufc' ] = [
					'title'   => $aScanNames[ 'ufc' ],
					'message' => __( 'Unrecognised files found in WordPress Core directory.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Scan and remove any files that are not meant to be in the WP core directories.', 'wp-simple-firewall' )
				];
			}
		}

		{// Plugin/Theme Guard
			$scan = $mod->getScanCon( 'ptg' );
			if ( !$scan->isEnabled() ) {
				$notices[ 'messages' ][ 'ptg' ] = [
					'title'   => $aScanNames[ 'ptg' ],
					'message' => __( 'Automatic Plugin/Themes Guard is not enabled.', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'ptg_enable' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of plugin/theme modifications is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $scan->getScanHasProblem() ) {
				$notices[ 'messages' ][ 'ptg' ] = [
					'title'   => $aScanNames[ 'ptg' ],
					'message' => __( 'A plugin/theme was found to have been modified.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Reviewing modifications to your plugins/themes is recommended.', 'wp-simple-firewall' )
				];
			}
		}

		{// Vulnerability Scanner
			$scan = $mod->getScanCon( 'wpv' );
			if ( !$scan->isEnabled() ) {
				$notices[ 'messages' ][ 'wpv' ] = [
					'title'   => $aScanNames[ 'wpv' ],
					'message' => __( 'Vulnerability Scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of vulnerabilities is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $scan->getScanHasProblem() ) {
				$notices[ 'messages' ][ 'wpv' ] = [
					'title'   => $aScanNames[ 'wpv' ],
					'message' => __( 'At least 1 item has known vulnerabilities.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Items with known vulnerabilities should be updated, removed, or replaced.', 'wp-simple-firewall' )
				];
			}
		}

		{// Abandoned Plugins
			$scan = $mod->getScanCon( 'apc' );
			if ( !$scan->isEnabled() ) {
				$notices[ 'messages' ][ 'apc' ] = [
					'title'   => $aScanNames[ 'apc' ],
					'message' => __( 'Abandoned Plugins Scanner is not enabled.', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_apc' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Automatic detection of abandoned plugins is recommended.', 'wp-simple-firewall' )
				];
			}
			elseif ( $scan->getScanHasProblem() ) {
				$notices[ 'messages' ][ 'apc' ] = [
					'title'   => $aScanNames[ 'apc' ],
					'message' => __( 'At least 1 plugin on your site is abandoned.', 'wp-simple-firewall' ),
					'href'    => $sScansUrl,
					'action'  => __( 'Run Scan', 'wp-simple-firewall' ),
					'rec'     => __( 'Plugins that have been abandoned represent a potential risk to your site.', 'wp-simple-firewall' )
				];
			}
		}

		return $notices;
	}

	protected function getSectionWarnings( string $section ) :array {
		$aWarnings = [];

		switch ( $section ) {

			case 'section_realtime':
				$bCanHandshake = $this->getCon()
									  ->getModule_Plugin()
									  ->getShieldNetApiController()
									  ->canHandshake();
				if ( !$bCanHandshake ) {
					$aWarnings[] = sprintf( __( 'Not available as your site cannot handshake with ShieldNET API.', 'wp-simple-firewall' ), 'OpenSSL' );
				}
//				if ( !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
//					$aWarnings[] = sprintf( __( 'Not available because the %s extension is not available.', 'wp-simple-firewall' ), 'OpenSSL' );
//				}
//				if ( !Services::WpFs()->isFilesystemAccessDirect() ) {
//					$aWarnings[] = sprintf( __( "Not available because PHP/WordPress doesn't have direct filesystem access.", 'wp-simple-firewall' ), 'OpenSSL' );
//				}
				break;
		}

		return $aWarnings;
	}
}