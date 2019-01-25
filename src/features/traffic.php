<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_FeatureHandler_Traffic extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		if ( $this->isAutoDisable() && $this->loadRequest()->ts() - $this->getAutoDisableAt() > 0 ) {
			$this->setOpt( 'auto_disable', 'N' )
				 ->setOpt( 'autodisable_at', 0 )
				 ->setIsMainFeatureEnabled( false );
		}
		parent::action_doFeatureShutdown();
	}

	/**
	 * We clean the database after saving.
	 */
	protected function doExtraSubmitProcessing() {
		/** @var ICWP_WPSF_Processor_Traffic $oPro */
		$oPro = $this->getProcessor();
		$oPro->getProcessorLogger()
			 ->cleanupDatabase();

		$this->setOpt( 'autodisable_at', $this->isAutoDisable() ? $this->loadRequest()->ts() + WEEK_IN_SECONDS : 0 );

		$aExcls = $this->getCustomExclusions();
		foreach ( $aExcls as &$sExcl ) {
			$sExcl = trim( esc_js( $sExcl ) );
		}
		$this->setOpt( 'custom_exclusions', array_filter( $aExcls ) );
	}

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		$oIp = $this->loadIpService();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) && parent::isReadyToExecute();
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = array();

		if ( !$this->isPremium() ) {
			$aWarnings[] = sprintf( _wpsf__( '%s is a Pro-only feature.' ), _wpsf__( 'Traffic Watch' ) );
		}
		else {
			$oIp = $this->loadIpService();
			if ( !$this->loadIpService()->isValidIp_PublicRange( $oIp->getRequestIp() ) ) {
				$aWarnings[] = _wpsf__( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.' );
			}
		}

		return $aWarnings;
	}

	/**
	 * @return int
	 */
	public function getAutoCleanDays() {
		return (int)$this->getOpt( 'auto_clean' );
	}

	/**
	 * @return array
	 */
	protected function getExclusions() {
		$aEx = $this->getOpt( 'type_exclusions' );
		return is_array( $aEx ) ? $aEx : array();
	}

	/**
	 * @return array
	 */
	public function getCustomExclusions() {
		$aEx = $this->getOpt( 'custom_exclusions' );
		return is_array( $aEx ) ? $aEx : array();
	}

	/**
	 * @return int
	 */
	public function getMaxEntries() {
		return (int)$this->getOpt( 'max_entries' );
	}

	/**
	 * @return int
	 */
	public function getAutoDisableAt() {
		return (int)$this->getOpt( 'autodisable_at' );
	}

	/**
	 * @return string
	 */
	protected function getAutoDisableTimestamp() {
		return $this->loadWp()->getTimeStampForDisplay( $this->getAutoDisableAt() );
	}

	/**
	 * @return bool
	 */
	public function isAutoDisable() {
		return $this->isOpt( 'auto_disable', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Ajax() {
		return !in_array( 'ajax', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Cron() {
		return !in_array( 'cron', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_LoggedInUser() {
		return !in_array( 'logged_in', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Search() {
		return !in_array( 'search', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Simple() {
		return !in_array( 'simple', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Uptime() {
		return !in_array( 'uptime', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isLogUsers() {
		return $this->isIncluded_LoggedInUser();
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( $this->loadRequest()->request( 'exec' ) ) {

				case 'render_table_traffic':
					$aAjaxResponse = $this->ajaxExec_BuildTableTraffic();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	private function ajaxExec_BuildTableTraffic() {
		/** @var ICWP_WPSF_Processor_Traffic $oPro */
		$oPro = $this->getProcessor();
		$oTableBuilder = ( new Shield\Tables\Build\Traffic() )
			->setMod( $this )
			->setDbHandler( $oPro->getProcessorLogger()->getDbHandler() )
			->setGeoIpDbSource( $this->getCon()->getPath_Assets( 'db/GeoIp2/GeoLite2-Country.mmdb' ) );

		return array(
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		);
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_traffic' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Monitor and review all requests to your site.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Required only if you need to review and investigate and monitor requests to your site' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_traffic_options' :
				$sTitle = _wpsf__( 'Traffic Watch Options' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'Provides finer control over the Traffic Watch system.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'These settings are dependent on your requirements.' ), _wpsf__( 'User Management' ) ) )
				);
				$sTitleShort = _wpsf__( 'Traffic Logging Options' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_traffic' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'type_exclusions' :
				$sName = _wpsf__( 'Traffic Log Exclusions' );
				$sSummary = _wpsf__( 'Select Which Types Of Requests To Exclude' );
				$sDescription = _wpsf__( "Select request types that you don't want to appear in the traffic viewer." )
								.'<br/>'._wpsf__( 'If a request matches any exclusion rule, it will not show on the traffic viewer.' );
				break;

			case 'custom_exclusions' :
				$sName = _wpsf__( 'Custom Exclusions' );
				$sSummary = _wpsf__( 'Provide Custom Traffic Exclusions' );
				$sDescription = _wpsf__( "For each entry, if the text is present in either the User Agent or request Path, it will be excluded." )
								.'<br/>'._wpsf__( 'Take a new line for each entry.' )
								.'<br/>'._wpsf__( 'Comparisons are case-insensitive.' );
				break;

			case 'auto_clean' :
				$sName = _wpsf__( 'Auto Expiry Cleaning' );
				$sSummary = _wpsf__( 'Enable Traffic Log Auto Expiry' );
				$sDescription = _wpsf__( 'DB cleanup will delete logs older than this maximum value (in days).' );
				break;

			case 'max_entries' :
				$sName = _wpsf__( 'Max Log Length' );
				$sSummary = _wpsf__( 'Maximum Traffic Log Length To Keep' );
				$sDescription = _wpsf__( 'DB cleanup will delete logs to maintain this maximum number of records.' );
				break;

			case 'auto_disable' :
				$sName = _wpsf__( 'Auto Disable' );
				$sSummary = _wpsf__( 'Auto Disable Traffic Logging After 1 Week' );

				if ( $this->isAutoDisable() ) {
					$sTimestamp = '<br/>'.sprintf( _wpsf__( 'Auto Disable At: %s' ), $this->getAutoDisableTimestamp() );
				}
				else {
					$sTimestamp = '';
				}
				$sDescription = _wpsf__( 'Turn on to prevent unnecessary long-term traffic logging.' )
								.'<br />'._wpsf__( 'Timer resets after options save.' )
								.$sTimestamp;
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}