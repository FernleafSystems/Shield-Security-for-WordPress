<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Traffic extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		if ( $this->isAutoDisable() && Services::Request()->ts() - $this->getAutoDisableAt() > 0 ) {
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

		$this->setOpt( 'autodisable_at', $this->isAutoDisable() ? Services::Request()->ts() + WEEK_IN_SECONDS : 0 );

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
		$oIp = Services::IP();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) && parent::isReadyToExecute();
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		if ( !$this->isPremium() ) {
			$aWarnings[] = sprintf( __( '%s is a Pro-only feature.', 'wp-simple-firewall' ), __( 'Traffic Watch', 'wp-simple-firewall' ) );
		}
		else {
			$oIp = Services::IP();
			if ( !$oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) ) {
				$aWarnings[] = __( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.', 'wp-simple-firewall' );
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
		return is_array( $aEx ) ? $aEx : [];
	}

	/**
	 * @return array
	 */
	public function getCustomExclusions() {
		$aEx = $this->getOpt( 'custom_exclusions' );
		return is_array( $aEx ) ? $aEx : [];
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
		return Services::WpGeneral()->getTimeStampForDisplay( $this->getAutoDisableAt() );
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
			switch ( Services::Request()->request( 'exec' ) ) {

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
			->setDbHandler( $oPro->getProcessorLogger()->getDbHandler() );

		return [
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		];
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
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and review all requests to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Required only if you need to review and investigate and monitor requests to your site', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_traffic_options' :
				$sTitle = __( 'Traffic Watch Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the Traffic Watch system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				$sTitleShort = __( 'Traffic Logging Options', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
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
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'type_exclusions' :
				$sName = __( 'Traffic Log Exclusions', 'wp-simple-firewall' );
				$sSummary = __( 'Select Which Types Of Requests To Exclude', 'wp-simple-firewall' );
				$sDescription = __( "Select request types that you don't want to appear in the traffic viewer.", 'wp-simple-firewall' )
								.'<br/>'.__( 'If a request matches any exclusion rule, it will not show on the traffic viewer.', 'wp-simple-firewall' );
				break;

			case 'custom_exclusions' :
				$sName = __( 'Custom Exclusions', 'wp-simple-firewall' );
				$sSummary = __( 'Provide Custom Traffic Exclusions', 'wp-simple-firewall' );
				$sDescription = __( "For each entry, if the text is present in either the User Agent or request Path, it will be excluded.", 'wp-simple-firewall' )
								.'<br/>'.__( 'Take a new line for each entry.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Comparisons are case-insensitive.', 'wp-simple-firewall' );
				break;

			case 'auto_clean' :
				$sName = __( 'Auto Expiry Cleaning', 'wp-simple-firewall' );
				$sSummary = __( 'Enable Traffic Log Auto Expiry', 'wp-simple-firewall' );
				$sDescription = __( 'DB cleanup will delete logs older than this maximum value (in days).', 'wp-simple-firewall' );
				break;

			case 'max_entries' :
				$sName = __( 'Max Log Length', 'wp-simple-firewall' );
				$sSummary = __( 'Maximum Traffic Log Length To Keep', 'wp-simple-firewall' );
				$sDescription = __( 'DB cleanup will delete logs to maintain this maximum number of records.', 'wp-simple-firewall' );
				break;

			case 'auto_disable' :
				$sName = __( 'Auto Disable', 'wp-simple-firewall' );
				$sSummary = __( 'Auto Disable Traffic Logging After 1 Week', 'wp-simple-firewall' );

				if ( $this->isAutoDisable() ) {
					$sTimestamp = '<br/>'.sprintf( __( 'Auto Disable At: %s', 'wp-simple-firewall' ), $this->getAutoDisableTimestamp() );
				}
				else {
					$sTimestamp = '';
				}
				$sDescription = __( 'Turn on to prevent unnecessary long-term traffic logging.', 'wp-simple-firewall' )
								.'<br />'.__( 'Timer resets after options save.', 'wp-simple-firewall' )
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