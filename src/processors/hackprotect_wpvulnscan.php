<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_WpVulnScan', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_HackProtect_WpVulnScan extends ICWP_WPSF_Processor_Base {

	/**
	 * @var string
	 */
	protected $sApiRootUrl;

	/**
	 */
	public function run() {
		$this->setupVulnScanCron();
		if ( $this->loadDataProcessor()->FetchGet( 'force_wpvulnscan' ) == 1 ) {
			$this->cron_dailyWpVulnScan();
		}
	}

	public function cron_dailyWpVulnScan() {
		$this->scanPlugins();
		$this->scanThemes();
	}

	protected function scanPlugins() {
		foreach ( $this->loadWp()->getPlugins() as $sFile => $aData ) {

			if ( empty( $aData[ 'Version' ] ) ) {
				continue; // we can't check if we have no version.
			}

			$aVulnerabilitiesData = $this->getVulnerabilityDataForPlugin( $sFile, $aData );
			/** @var stdClass $oVulnerabilityData */
			foreach ( $aVulnerabilitiesData as $oSingleVulnerabilityData ) {
				$bVulnerable = $this->getIsVulnerable( $aData[ 'Version' ], $oSingleVulnerabilityData );
			}
		}
	}

	protected function scanThemes() {
		/** @var WP_Theme $oTheme */
		foreach ( $this->loadWp()->getThemes() as $sStylesheet => $oTheme ) {

			if ( empty( $oTheme->version ) ) {
				continue; // we can't check if we have no version.
			}
			$aVulnerabilitiesData = $this->getVulnerabilityDataForTheme( $oTheme );
			/** @var stdClass $oVulnerabilityData */
			foreach ( $aVulnerabilitiesData as $oSingleVulnerabilityData ) {
				$bVulnerable = $this->getIsVulnerable( $oTheme->version, $oSingleVulnerabilityData );
			}
		}
	}

	/**
	 * @param $sVersion
	 * @param $oVulnerabilityData
	 * @return mixed
	 */
	protected function getIsVulnerable( $sVersion, $oVulnerabilityData ) {
		$sFixedVersion = empty( $oVulnerabilityData->fixed_in ) ? '0' : $oVulnerabilityData->fixed_in;
		return version_compare( $sVersion, $sFixedVersion, '<' );
	}

	/**
	 * wpvulndb_api_url_wordpress: 'https://wpvulndb.com/api/v2/wordpresses/'
	 * wpvulndb_api_url_plugins: 'https://wpvulndb.com/api/v2/plugins/'
	 * wpvulndb_api_url_themes: 'https://wpvulndb.com/api/v2/themes/'
	 * @param $sPluginFile
	 * @param $aPluginData
	 * @return array
	 */
	protected function getVulnerabilityDataForPlugin( $sPluginFile, $aPluginData ) {
		$sSlug = !empty( $aPluginData[ 'slug' ] ) ? $aPluginData[ 'slug' ] : substr( $sPluginFile, 0, strpos( $sPluginFile, ICWP_DS ) );
		if ( empty( $sSlug ) ) {
			return array();
		}

		$oWp = $this->loadWp();
		$sTransientKey = $this->getFeature()->prefixOptionKey( 'wpvulnplugin-'.$sSlug );

		$sFullContent = $oWp->getTransient( $sTransientKey );
		if ( empty( $sFullContent ) ) {
			$sUrl = $this->getApiRootUrl().'plugins/'.$sSlug;
			$sFullContent = $this->loadFS()->getUrlContent( $sUrl );
		}

		$oWp->setTransient( $sTransientKey, $sFullContent, DAY_IN_SECONDS );

		if ( !empty( $sFullContent ) ) {
			$oData = json_decode( $sFullContent );
			if ( isset( $oData->{$sSlug} ) && !empty( $oData->{$sSlug}->vulnerabilities ) && is_array( $oData->{$sSlug}->vulnerabilities ) ) {
				return $oData->{$sSlug}->vulnerabilities;
			}
		}
		return array();
	}

	/**
	 * @param WP_Theme $oTheme
	 * @return array
	 */
	protected function getVulnerabilityDataForTheme( $oTheme ) {

		$sSlug = $oTheme->get_stylesheet();
		$oWp = $this->loadWp();
		$sTransientKey = $this->getFeature()->prefixOptionKey( 'wpvulntheme-'.$sSlug );

		$sFullContent = $oWp->getTransient( $sTransientKey );
		if ( empty( $sFullContent ) ) {
			$sUrl = $this->getApiRootUrl().'themes/'.$sSlug;
			$sFullContent = $this->loadFS()->getUrlContent( $sUrl );
		}

		$oWp->setTransient( $sTransientKey, $sFullContent, DAY_IN_SECONDS );

		if ( !empty( $sFullContent ) ) {
			$oData = json_decode( $sFullContent );
			if ( isset( $oData->{$sSlug} ) && !empty( $oData->{$sSlug}->vulnerabilities ) && is_array( $oData->{$sSlug}->vulnerabilities ) ) {
				return $oData->{$sSlug}->vulnerabilities;
			}
		}
		return array();
	}

	protected function setupVulnScanCron() {
		$oWpCron = $this->loadWpCronProcessor();
		$oWpCron->createCronJob( $this->getCronName(), array( $this, 'cron_dailyWpVulnScan' ) );
		add_action( $this->getFeature()->prefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 * @return string
	 */
	protected function getApiRootUrl() {
		if ( empty( $this->sApiRootUrl ) ) {
			$this->sApiRootUrl = rtrim( $this->getFeature()->getDefinition( 'wpvulndb_api_url_root' ), '/' ).'/';
		}
		return $this->sApiRootUrl;
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		$oFO = $this->getFeature();
		return $oFO->prefixOptionKey( $oFO->getDefinition( 'wpvulnscan_cron_name' ) );
	}
}