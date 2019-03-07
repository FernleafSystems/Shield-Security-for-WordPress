<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Bottrap extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
	}

	/**
	 */
	protected function updateHandler() {
		// v7.3
		if ( $this->isPremium() && !$this->isEnabled404() ) {
			/** @var ICWP_WPSF_FeatureHandler_Ips $oIp */
			$oIp = $this->getCon()->getModule( 'ips' );
			if ( $oIp->getOptTracking404() === 'assign-transgression' ) {
				$this->setOpt( '404_detect', 'transgression' );
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isEnabled404() {
		return $this->isSelectOptionEnabled( '404_detect' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledFakeWebCrawler() {
		return $this->isSelectOptionEnabled( 'fake_webcrawler' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledInvalidUsernames() {
		return $this->isSelectOptionEnabled( 'invalid_username' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledFailedLogins() {
		return $this->isSelectOptionEnabled( 'failed_login' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledLinkCheese() {
		return $this->isSelectOptionEnabled( 'link_cheese' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledXmlRpcDetect() {
		return $this->isSelectOptionEnabled( 'xmlrpc' );
	}

	/**
	 * @return bool
	 */
	public function isTransgression404() {
		return $this->isSelectOptionTransgression( '404_detect' );
	}

	/**
	 * @return bool
	 */
	public function isTransgressionLinkCheese() {
		return $this->isSelectOptionTransgression( 'link_cheese' );
	}

	/**
	 * @return bool
	 */
	public function isTransgressionInvalidUsernames() {
		return $this->isSelectOptionTransgression( 'invalid_username' );
	}

	/**
	 * @return bool
	 */
	public function isTransgressionFailedLogins() {
		return $this->isSelectOptionTransgression( 'failed_login' );
	}

	/**
	 * @return bool
	 */
	public function isTransgressionFakeWebCrawler() {
		return $this->isSelectOptionTransgression( 'fake_webcrawler' );
	}

	/**
	 * @return bool
	 */
	public function isTransgressionXmlRpc() {
		return $this->isSelectOptionTransgression( 'xmlrpc' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	protected function isSelectOptionTransgression( $sOptionKey ) {
		return $this->isOpt( $sOptionKey, 'transgression' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	protected function isSelectOptionEnabled( $sOptionKey ) {
		return !$this->isOpt( $sOptionKey, 'disabled' );
	}

	/**
	 * @return bool
	 */
	private function getMouseTrapResponseType() {
		return $this->getOpt( 'mousetrap_bot_response' );
	}

	/**
	 * @return bool
	 */
	public function isMouseTrapEnabled() {
		return $this->isPremium() && ( $this->getMouseTrapResponseType() != 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isMouseTrayBlock() {
		return $this->getMouseTrapResponseType() === 'block';
	}

	/**
	 * @return bool
	 */
	public function isMouseTrapTransgression() {
		return $this->getMouseTrapResponseType() === 'transgression';
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_enable_plugin_feature_bottrap' :
				$sTitle = _wpsf__( 'Identify And Capture Bots Based On Their Site Activity' );
				$aSummary = array(
					_wpsf__( "A bot doesn't know what's real and what's not, so it probes many different avenues until it finds something it recognises." ),
					_wpsf__( "Bot-Trap monitors a set of typical bot behaviours to help identify probing bots." ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Enable as many mouse traps as possible.' ) )
				);
				$sTitleShort = _wpsf__( 'Bot-Trap' );
				break;

			case 'section_logins':
				$sTitle = _wpsf__( 'Capture Login Bots' );
				$sTitleShort = _wpsf__( 'Login Bots' );
				$aSummary = [
					sprintf( '%s - %s', _wpsf__( 'Summary' ),
						_wpsf__( "Certain bots are designed to test your logins and this feature lets you decide how to handle them." ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ),
						_wpsf__( "Enable as many options as possible." ) ),
					sprintf( '%s - %s', _wpsf__( 'Warning' ),
						_wpsf__( "Legitimate users may get their password wrong, so take care not to block this." ) ),
				];
				break;

			case 'section_probes':
				$sTitle = _wpsf__( 'Capture Probing Bots' );
				$sTitleShort = _wpsf__( 'Probing Bots' );
				$aSummary = [
					sprintf( '%s - %s', _wpsf__( 'Summary' ),
						_wpsf__( "Bots are designed to probe and this feature is dedicated to detecting probing bots." ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ),
						_wpsf__( "Enable as many options as possible." ) ),
				];
				break;

			case 'section_behaviours':
				$sTitle = _wpsf__( 'Identify Common Bot Behaviours' );
				$sTitleShort = _wpsf__( 'Bot Behaviours' );
				$aSummary = [
					sprintf( '%s - %s', _wpsf__( 'Summary' ),
						_wpsf__( "Detect characteristics and behaviour commonly associated with illegitimate bots." ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ),
						_wpsf__( "Enable as many options as possible." ) ),
				];
				break;

			default:
				list( $sTitle, $sTitleShort, $aSummary ) = $this->loadStrings_SectionTitlesDefaults( $aOptionsParams );
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

		switch ( $aOptionsParams[ 'key' ] ) {

			case 'enable_bottrap' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case '404_detect' :
				$sName = _wpsf__( '404 Detect' );
				$sSummary = _wpsf__( 'Identify A Bot When It Hits A 404' );
				$sDescription = _wpsf__( "Detect when a visitor tries to load a non-existent page." )
								.'<br/>'._wpsf__( "Care should be taken to ensure you don't have legitimate links on your site that are 404s." );
				break;

			case 'xmlrpc' :
				$sName = _wpsf__( 'XML-RPC Access' );
				$sSummary = _wpsf__( 'Identify A Bot When It Accesses XML-RPC' );
				$sDescription = _wpsf__( "If you don't use XML-RPC, why would anyone access it?" )
								.'<br/>'._wpsf__( "Be careful the ensure you don't block legitimate xml-rpc traffic if your site needs it." );
				break;

			case 'link_cheese' :
				$sName = _wpsf__( 'Link Cheese' );
				$sSummary = _wpsf__( 'Tempt A Bot With A Link To Follow' );
				$sDescription = _wpsf__( "Detect a bot when it follows a 'no-follow' link." );
				break;

			case 'invalid_username' :
				$sName = _wpsf__( 'Invalid Usernames' );
				$sSummary = _wpsf__( 'Detect Invalid Username Logins' );
				$sDescription = _wpsf__( "Identify a Bot when it tries to login with a non-existent username" );
				break;

			case 'failed_login' :
				$sName = _wpsf__( 'Failed Login' );
				$sSummary = _wpsf__( 'Detect Failed Login Attempts Using Valid Usernames' );
				$sDescription = _wpsf__( "Penalise a visitor when they try to login using a valid username, but it fails." );
				break;

			case 'fake_webcrawler' :
				$sName = _wpsf__( 'Fake Web Crawler' );
				$sSummary = _wpsf__( 'Detect Fake Search Engine Crawlers' );
				$sDescription = _wpsf__( "Identify a Bot when it presents as an official web crawler, but analysis shows it's fake." );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $aOptionsParams[ 'key' ] ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}