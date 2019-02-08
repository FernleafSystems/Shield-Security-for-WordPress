<?php

class ICWP_WPSF_FeatureHandler_Mousetrap extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	protected function doPostConstruction() {
	}

	/**
	 * @return bool
	 */
	private function getMouseTrapResponseType() {
		return $this->getOpt( 'mousetrap_bot_response' );
	}

	/**
	 * @return string
	 */
	public function getMouseTrapKey() {
		$sKey = $this->getOpt( 'mousetrap_key' );
		if ( empty( $sKey ) ) {
			$sKey = substr( md5( wp_generate_password() ), 5, 6 );
			$this->setOpt( 'mousetrap_key', $sKey );
		}
		return $sKey;
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

			case 'section_enable_plugin_feature_mousetrap' :
				$sTitle = _wpsf__( 'Identify And Capture Bots Based On Their Site Activity' );
				$aSummary = array(
					_wpsf__( "A bot doesn't know what's real and what's not, so it probes many different avenues until it finds something it recognises." ),
					_wpsf__( "MouseTrap monitors a set of typical bot behaviours to help identify probing bots." ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Enable as many mouse traps as possible.' ) )
				);
				$sTitleShort = _wpsf__( 'Bot MouseTrap' );
				break;

			case 'section_cheese' :
				$sTitle = _wpsf__( 'Capture Bot Activity' );
				$aSummary = [
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( "Enable as many options as possible." ) )
				];
				$sTitleShort = _wpsf__( 'Bot Cheeses' );
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

			case 'enable_mousetrap' :
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