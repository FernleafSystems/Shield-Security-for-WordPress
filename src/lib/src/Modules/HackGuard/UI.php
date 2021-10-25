<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function buildInsightsVars_Results() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		foreach ( $opts->getScanSlugs() as $scan ) {
			$mod->getScanCon( $scan )->cleanStalesResults();
		}

		$sectionBuilderPlugins = ( new Render\ScanResults\SectionPlugins() )->setMod( $this->getMod() );
		$sectionBuilderThemes = ( new Render\ScanResults\SectionThemes() )->setMod( $this->getMod() );
		$sectionBuilderWordpress = ( new Render\ScanResults\SectionWordpress() )->setMod( $this->getMod() );
		$sectionBuilderMalware = ( new Render\ScanResults\SectionMalware() )->setMod( $this->getMod() );
//		$sectionBuilderLog = ( new Render\ScanResults\SectionMalware() )->setMod( $this->getMod() );

		// Can Scan Checks:
		$reasonsCantScan = $mod->getScansCon()->getReasonsScansCantExecute();
		return [
			'ajax'        => [
				'scans_start' => $mod->getAjaxActionData( 'scans_start', true ),
				'scans_check' => $mod->getAjaxActionData( 'scans_check', true ),
			],
			'flags'       => [
				'is_premium'      => $this->getCon()->isPremiumActive(),
				'can_scan'        => count( $reasonsCantScan ) === 0,
				'module_disabled' => !$mod->isModOptEnabled(),
			],
			'strings'     => [
				'never'                 => __( 'Never', 'wp-simple-firewall' ),
				'not_available'         => __( 'Sorry, this scan is not available.', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
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
				'module_disabled'       => __( "Scans can't run because the module that controls them is currently disabled.", 'wp-simple-firewall' ),
				'review_scanner_config' => __( "Review Scanner Module configuration", 'wp-simple-firewall' ),
			],
			'vars'        => [
				'initial_check'       => $mod->getScanQueueController()->hasRunningScans(),
				'cannot_scan_reasons' => $reasonsCantScan,
				'sections'            => [
					'plugins'   => [
						'count' => $sectionBuilderPlugins->getRenderData()[ 'vars' ][ 'count_items' ]
					],
					'themes'    => [
						'count' => $sectionBuilderThemes->getRenderData()[ 'vars' ][ 'count_items' ]
					],
					'wordpress' => [
						'count' => $sectionBuilderWordpress->getRenderData()[ 'vars' ][ 'count_items' ]
					],
					'malware'   => [
						'count' => $sectionBuilderMalware->getRenderData()[ 'vars' ][ 'count_items' ]
					],
				]
			],
			'hrefs'       => [
				'scanner_mod_config' => $mod->getUrl_DirectLinkToSection( 'section_enable_plugin_feature_hack_protection_tools' ),
				'scans_results'      => $this->getCon()
											 ->getModule_Insights()
											 ->getUrl_ScansResults(),
			],
			'content'     => [
				'section' => [
					'plugins'   => $sectionBuilderPlugins->render(),
					'themes'    => $sectionBuilderThemes->render(),
					'wordpress' => $sectionBuilderWordpress->render(),
					'malware'   => $sectionBuilderMalware->render(),
					'logs'      => 'logs todo',
				]
			],
			'file_locker' => $this->getFileLockerVars(),
		];
	}

	public function buildInsightsVars_Run() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		// Can Scan Checks:
		$reasonsCantScan = $mod->getScansCon()->getReasonsScansCantExecute();
		return [
			'ajax'        => [
				'scans_start' => $mod->getAjaxActionData( 'scans_start', true ),
				'scans_check' => $mod->getAjaxActionData( 'scans_check', true ),
			],
			'flags'       => [
				'is_premium'      => $this->getCon()->isPremiumActive(),
				'can_scan'        => count( $reasonsCantScan ) === 0,
				'module_disabled' => !$mod->isModOptEnabled(),
			],
			'strings'     => [
				'never'                 => __( 'Never', 'wp-simple-firewall' ),
				'not_available'         => __( 'Sorry, this scan is not available.', 'wp-simple-firewall' ),
				'not_enabled'           => __( 'This scan is not currently enabled.', 'wp-simple-firewall' ),
				'please_enable'         => __( 'Please turn on this scan in the options.', 'wp-simple-firewall' ),
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
				'module_disabled'       => __( "Scans can't run because the module that controls them is currently disabled.", 'wp-simple-firewall' ),
				'review_scanner_config' => __( "Review Scanner Module configuration", 'wp-simple-firewall' ),
			],
			'scans'       => $this->buildScansVars(),
			'vars'        => [
				'initial_check'       => $mod->getScanQueueController()->hasRunningScans(),
				'cannot_scan_reasons' => $reasonsCantScan,
			],
			'hrefs'       => [
				'scanner_mod_config' => $mod->getUrl_DirectLinkToSection( 'section_enable_plugin_feature_hack_protection_tools' ),
			],
			'content'     => [
				'section' => [
				]
			],
			'file_locker' => $this->getFileLockerVars(),
		];
	}

	private function buildScansVars() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var Strings $strings */
		$strings = $mod->getStrings();
		$scanStrings = $strings->getScanStrings();
		$scans = [];
		foreach ( $mod->getScansCon()->getAllScanCons() as $scanCon ) {
			$slug = $scanCon->getSlug();
			$data = [
				'flags'   => [
					'is_available'  => $scanCon->isReady(),
					'is_restricted' => $scanCon->isRestricted(),
					'is_enabled'    => $scanCon->isEnabled(),
					'is_selected'   => $scanCon->isReady()
									   && in_array( $slug, $mod->getUiTrack()->selected_scans ),
				],
				'strings' => [
					'title'    => $scanStrings[ $slug ][ 'name' ],
					'subtitle' => $scanStrings[ $slug ][ 'subtitle' ],
				],
				'vars'    => [
					'slug' => $scanCon->getSlug(),
				],
			];
			$scans[ $slug ] = $data;
		}

		return $scans;
	}

	/**
	 * @param array $option
	 * @return array
	 */
	protected function buildOptionForUi( $option ) {
		$option = parent::buildOptionForUi( $option );
		if ( $option[ 'key' ] === 'file_locker' && !Services::Data()->isWindows() ) {
			$option[ 'value_options' ][ 'root_webconfig' ] .= sprintf( ' (%s)', __( 'unavailable', 'wp-simple-firewall' ) );
		}
		return $option;
	}

	protected function getFileLockerVars() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$lockerCon = $mod->getFileLocker();
		$lockLoader = ( new Lib\FileLocker\Ops\LoadFileLocks() )->setMod( $mod );
		$problemLocks = $lockLoader->withProblems();
		$goodLocks = $lockLoader->withoutProblems();

		return [
			'ajax'    => [
				'filelocker_showdiff'   => $mod->getAjaxActionData( 'filelocker_showdiff', true ),
				'filelocker_fileaction' => $mod->getAjaxActionData( 'filelocker_fileaction', true ),
			],
			'flags'   => [
				'is_enabled'    => $lockerCon->isEnabled(),
				'is_restricted' => !$this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'options'       => $mod->getUrl_DirectLinkToSection( 'section_realtime' ),
				'please_enable' => $mod->getUrl_DirectLinkToSection( 'section_realtime' ),
			],
			'vars'    => [
				'file_locks' => [
					'good'        => $goodLocks,
					'bad'         => $problemLocks,
					'count_items' => count( $problemLocks ),
				],
			],
			'strings' => [
				'title'         => __( 'File Locker', 'wp-simple-firewall' ),
				'subtitle'      => __( 'Results of file locker monitoring', 'wp-simple-firewall' ),
				'please_select' => __( 'Please select a file to review.', 'wp-simple-firewall' ),
			],
			'count'   => count( $problemLocks )
		];
	}

	protected function getSectionWarnings( string $section ) :array {
		$warnings = [];

		switch ( $section ) {

			case 'section_realtime':
				$canHandshake = $this->getCon()
									 ->getModule_Plugin()
									 ->getShieldNetApiController()
									 ->canHandshake();
				if ( !$canHandshake ) {
					$warnings[] = sprintf( __( 'Not available as your site cannot handshake with ShieldNET API.', 'wp-simple-firewall' ), 'OpenSSL' );
				}
				if ( !$this->getCon()->hasCacheDir() ) {
					$warnings[] = __( "Certain scanners are unavailable because we couldn't create a temporary directory to store files.", 'wp-simple-firewall' );
				}
//				if ( !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
//					$warnings[] = sprintf( __( 'Not available because the %s extension is not available.', 'wp-simple-firewall' ), 'OpenSSL' );
//				}
//				if ( !Services::WpFs()->isFilesystemAccessDirect() ) {
//					$warnings[] = sprintf( __( "Not available because PHP/WordPress doesn't have direct filesystem access.", 'wp-simple-firewall' ), 'OpenSSL' );
//				}
				break;
		}

		return $warnings;
	}
}