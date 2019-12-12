<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Wpv extends ICWP_WPSF_Processor_HackProtect_ScanAssetsBase {

	const SCAN_SLUG = 'wpv';

	/**
	 * @var int
	 */
	private $nColumnsCount;

	/**
	 */
	public function run() {
		parent::run();

		// For display on the Plugins page
		add_action( 'load-plugins.php', [ $this, 'addPluginVulnerabilityRows' ], 10, 2 );
		add_action( 'upgrader_process_complete', [ $this, 'hookOnDemandScan' ], 10, 0 );
		add_action( 'deleted_plugin', [ $this, 'hookOnDemandScan' ], 10, 0 );

		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isWpvulnAutoupdatesEnabled() ) {
			add_filter( 'auto_update_plugin', [ $this, 'autoupdateVulnerablePlugins' ], PHP_INT_MAX, 2 );
		}
	}

	/**
	 * @param Wpv\ResultsSet $oRes
	 * @return bool - true if user notified
	 */
	protected function runCronUserNotify( $oRes ) {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$bSend = $oOpts->isOpt( 'enable_wpvuln_scan', 'enabled_email' );
		if ( $bSend ) {
			$this->emailResults( $oRes );
		}
		return $bSend;
	}

	/**
	 * @param bool            $bDoAutoUpdate
	 * @param StdClass|string $mItem
	 * @return bool
	 */
	public function autoupdateVulnerablePlugins( $bDoAutoUpdate, $mItem ) {
		$sItemFile = Services::WpGeneral()->getFileFromAutomaticUpdateItem( $mItem );
		// TODO Audit.
		return $bDoAutoUpdate || ( $this->getPluginVulnerabilities( $sItemFile ) > 0 );
	}

	/**
	 * @param array $aColumns
	 * @return array
	 */
	public function fCountColumns( $aColumns ) {
		if ( !isset( $this->nColumnsCount ) ) {
			$this->nColumnsCount = count( $aColumns );
		}
		return $aColumns;
	}

	public function addPluginVulnerabilityRows() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isWpvulnPluginsHighlightEnabled() && $this->countVulnerablePlugins() > 0 ) {
			// These 3 add the 'Vulnerable' plugin status view.
			// BUG: when vulnerable is active, only 1 plugin is available to "All" status. don't know fix.
			add_action( 'pre_current_active_plugins', [ $this, 'addVulnerablePluginStatusView' ], 1000 );
			add_filter( 'all_plugins', [ $this, 'filterPluginsToView' ], 1000 );
			add_filter( 'views_plugins', [ $this, 'addPluginsStatusViewLink' ], 1000 );
			add_filter( 'manage_plugins_columns', [ $this, 'fCountColumns' ], 1000 );
			foreach ( Services::WpPlugins()->getInstalledPluginFiles() as $sPluginFile ) {
				add_action( "after_plugin_row_$sPluginFile", [ $this, 'attachVulnerabilityWarning' ], 100, 2 );
			}
		}
	}

	public function addVulnerablePluginStatusView() {
		if ( Services::Request()->query( 'plugin_status' ) == 'vulnerable' ) {
			global $status;
			$status = 'vulnerable';
		}
		add_filter( 'views_plugins', [ $this, 'addPluginsStatusViewLink' ], 1000 );
	}

	/**
	 * FILTER
	 * @param array $aViews
	 * @return array
	 */
	public function addPluginsStatusViewLink( $aViews ) {
		global $status;

		$aViews[ 'vulnerable' ] = sprintf( "<a href='%s' %s>%s</a>",
			add_query_arg( 'plugin_status', 'vulnerable', 'plugins.php' ),
			( 'vulnerable' === $status ) ? ' class="current"' : '',
			sprintf( '%s <span class="count">(%s)</span>',
				__( 'Vulnerable', 'wp-simple-firewall' ),
				number_format_i18n( $this->countVulnerablePlugins() )
			)
		);
		return $aViews;
	}

	/**
	 * FILTER
	 * @param array $aPlugins
	 * @return array
	 */
	public function filterPluginsToView( $aPlugins ) {
		if ( Services::Request()->query( 'plugin_status' ) == 'vulnerable' ) {
			/** @var Wpv\ResultsSet $oVulnerableRes */
			$oVulnerableRes = $this->getThisScanCon()->getAllResults();
			global $status;
			$status = 'vulnerable';
			$aPlugins = array_intersect_key(
				$aPlugins,
				array_flip( $oVulnerableRes->getUniqueSlugs() )
			);
		}
		return $aPlugins;
	}

	/**
	 * @param string $sPluginFile
	 * @param array  $aPluginData
	 */
	public function attachVulnerabilityWarning( $sPluginFile, $aPluginData ) {

		$aVuln = $this->getPluginVulnerabilities( $sPluginFile );
		if ( count( $aVuln ) ) {
			$sOurName = $this->getCon()->getHumanName();
			$aRenderData = [
				'strings'  => [
					'known_vuln'     => sprintf( __( '%s has discovered that the currently installed version of the %s plugin has known security vulnerabilities.', 'wp-simple-firewall' ),
						$sOurName, '<strong>'.$aPluginData[ 'Name' ].'</strong>' ),
					'name'           => __( 'Vulnerability Name', 'wp-simple-firewall' ),
					'type'           => __( 'Vulnerability Type', 'wp-simple-firewall' ),
					'fixed_versions' => __( 'Fixed Versions', 'wp-simple-firewall' ),
					'more_info'      => __( 'More Info', 'wp-simple-firewall' ),
				],
				'vulns'    => $aVuln,
				'nColspan' => $this->nColumnsCount
			];
			echo $this->getMod()
					  ->renderTemplate( 'snippets/plugin-vulnerability.php', $aRenderData );
		}
	}

	/**
	 * @param Shield\Scans\Wpv\ResultsSet $oRes
	 */
	protected function emailResults( $oRes ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oWpPlugins = Services::WpPlugins();
		$oWpThemes = Services::WpThemes();
		$oCon = $this->getCon();

		$aContent = [
			sprintf( __( '%s has detected items with known security vulnerabilities.', 'wp-simple-firewall' ), $oCon->getHumanName() ),
			__( 'You should update or remove these items at your earliest convenience.', 'wp-simple-firewall' ),
			__( 'Details for the items(s) are below:', 'wp-simple-firewall' ),
			'',
		];

		/** @var Shield\Scans\Wpv\ResultItem $oItem */
		foreach ( $oRes->getItems() as $oItem ) {

			if ( $oItem->context == 'plugins' ) {
				$aPlugin = $oWpPlugins->getPlugin( $oItem->slug );
				$sName = sprintf( '%s - %s', __( 'Plugin', 'wp-simple-firewall' ), empty( $aPlugin ) ? 'Unknown' : $aPlugin[ 'Name' ] );
			}
			else {
				$sName = sprintf( '%s - %s', __( 'Theme', 'wp-simple-firewall' ), $oWpThemes->getCurrentThemeName() );
			}

			$oVuln = $oItem->getWpVulnVo();
			$aContent[] = implode( "<br />", [
				sprintf( '%s: %s', __( 'Item', 'wp-simple-firewall' ), $sName ),
				'- '.sprintf( __( 'Vulnerability Title: %s', 'wp-simple-firewall' ), $oVuln->title ),
				'- '.sprintf( __( 'Vulnerability Type: %s', 'wp-simple-firewall' ), $oVuln->vuln_type ),
				'- '.sprintf( __( 'Fixed Version: %s', 'wp-simple-firewall' ), $oVuln->fixed_in ),
				'- '.sprintf( __( 'Further Information: %s', 'wp-simple-firewall' ), $oVuln->getUrl() ),
				'',
			] );
		}

		$aContent[] = $this->getScannerButtonForEmail();
		$aContent[] = '';

		$sSubject = sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Plugin(s) Discovered With Known Security Vulnerabilities.', 'wp-simple-firewall' ) );
		$sTo = $oMod->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap( $sTo, $sSubject, $aContent );

		$this->getCon()->fireEvent(
			'wpv_alert_sent',
			[
				'audit' => [
					'to'  => $sTo,
					'via' => 'email',
				]
			]
		);
	}

	/**
	 * @param string $sFile
	 * @return Shield\Scans\Wpv\WpVulnDb\WpVulnVO[]
	 */
	private function getPluginVulnerabilities( $sFile ) {
		/** @var Wpv\ResultsSet $oVulnerableRes */
		$oVulnerableRes = $this->getThisScanCon()->getAllResults();
		return array_map(
			function ( $oItem ) {
				/** @var Shield\Scans\Wpv\ResultItem $oItem */
				return $oItem->getWpVulnVo();
			},
			$oVulnerableRes->getItemsForSlug( $sFile )
		);
	}

	/**
	 * @return bool
	 */
	private function countVulnerablePlugins() {
		/** @var Wpv\ResultsSet $oVulnerableRes */
		$oVulnerableRes = $this->getThisScanCon()->getAllResults();
		return $oVulnerableRes->countUniqueSlugsForPluginsContext();
	}
}