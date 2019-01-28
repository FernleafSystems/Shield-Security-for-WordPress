<?php

class ICWP_WPSF_FeatureHandler_Firewall extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return array
	 */
	public function getDefaultWhitelist() {
		$aW = $this->getDef( 'default_whitelist' );
		return is_array( $aW ) ? $aW : array();
	}

	/**
	 * @param string $sParam
	 * @param string $sPage
	 * @return ICWP_WPSF_FeatureHandler_Firewall
	 */
	public function addParamToWhitelist( $sParam, $sPage = '*' ) {
		if ( empty( $sPage ) ) {
			$sPage = '*';
		}

		$aW = $this->getCustomWhitelist();
		$aParams = isset( $aW[ $sPage ] ) ? $aW[ $sPage ] : array();
		$aParams[] = $sParam;
		natsort( $aParams );
		$aW[ $sPage ] = array_unique( $aParams );

		return $this->setOpt( 'page_params_whitelist', $aW );
	}

	/**
	 * @return array
	 */
	public function getCustomWhitelist() {
		$aW = $this->getOpt( 'page_params_whitelist', array() );
		return is_array( $aW ) ? $aW : array();
	}

	/**
	 * @return string
	 */
	public function getBlockResponse() {
		$sBlockResponse = $this->getOpt( 'block_response', '' );
		return !empty( $sBlockResponse ) ? $sBlockResponse : 'redirect_die_message'; // TODO: use default
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {
			case 'text_firewalldie':
				$sText = sprintf(
					_wpsf__( "You were blocked by the %s." ),
					'<a href="https://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'.$this->getCon()
																										->getHumanName().'</a>'
				);
				break;

			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	/**
	 * @return bool
	 */
	public function isIgnoreAdmin() {
		return $this->isOpt( 'whitelist_admins', 'Y' );
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = array(
			'strings'      => array(
				'title' => _wpsf__( 'Firewall' ),
				'sub'   => _wpsf__( 'Block Malicious Requests' ),
			),
			'key_opts'     => array(),
			'href_options' => $this->getUrl_AdminPage()
		);

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$aThis[ 'key_opts' ][ 'mod' ] = array(
				'name'    => _wpsf__( 'Firewall' ),
				'enabled' => $this->isModOptEnabled(),
				'summary' => $this->isModOptEnabled() ?
					_wpsf__( 'Your site is protected against malicious requests' )
					: _wpsf__( 'Your site is not protected against malicious requests' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( $this->getEnableModOptKey() ),
			);

			//ignoring admin isn't a good idea
			$bAdminIncluded = !$this->isIgnoreAdmin();
			$aThis[ 'key_opts' ][ 'admin' ] = array(
				'name'    => _wpsf__( 'Ignore Admins' ),
				'enabled' => $bAdminIncluded,
				'summary' => $bAdminIncluded ?
					_wpsf__( "Firewall rules are also applied to admins" )
					: _wpsf__( "Firewall rules aren't applied to admins" ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToOption( 'whitelist_admins' ),
			);
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_enable_plugin_feature_wordpress_firewall' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'The Firewall is designed to analyse data sent to your website and block any requests that appear to be malicious.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Firewall' ) ) )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_firewall_blocking_options' :
				$sTitle = _wpsf__( 'Firewall Blocking Options' );
				$aSummary = array(
					_wpsf__( 'Here you choose what kind of malicious data to scan for.' ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ),
						_wpsf__( 'Turn on as many options here as you can.' ) )
					.' '._wpsf__( 'If you find an incompatibility or something stops working, un-check 1 option at a time until you find the problem or review the Audit Trail.' ),
				);
				$sTitleShort = _wpsf__( 'Firewall Blocking' );
				break;

			case 'section_choose_firewall_block_response' :
				$sTitle = _wpsf__( 'Choose Firewall Block Response' );
				$aSummary = array(
					_wpsf__( 'Here you choose how the plugin will respond when it detects malicious data.' ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Choose the option "%s".' ), _wpsf__( 'Die With Message' ) ) )
				);
				$sTitleShort = _wpsf__( 'Firewall Response' );
				break;

			case 'section_whitelist' :
				$sTitle = _wpsf__( 'Whitelists - IPs, Pages, Parameters, and Users that by-pass the Firewall' );
				$aSummary = array(
					_wpsf__( 'In principle you should not need to whitelist anything or anyone unless you have discovered a collision with another plugin.' ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), _wpsf__( 'Do not whitelist anything unless you are confident in what you are doing.' ) )
				);
				$sTitleShort = _wpsf__( 'Whitelist' );
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

			case 'enable_firewall' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'include_cookie_checks' :
				$sName = _wpsf__( 'Include Cookies' );
				$sSummary = _wpsf__( 'Also Test Cookie Values In Firewall Tests' );
				$sDescription = _wpsf__( 'The firewall tests GET and POST, but with this option checked it will also check COOKIE values.' );
				break;

			case 'block_dir_traversal' :
				$sName = _wpsf__( 'Directory Traversals' );
				$sSummary = _wpsf__( 'Block Directory Traversals' );
				$sDescription = sprintf( _wpsf__( 'This will block directory traversal paths in in application parameters (e.g. %s, etc).' ), base64_decode( 'Li4vLCAuLi8uLi9ldGMvcGFzc3dk' ) );
				break;

			case 'block_sql_queries' :
				$sName = _wpsf__( 'SQL Queries' );
				$sSummary = _wpsf__( 'Block SQL Queries' );
				$sDescription = sprintf( _wpsf__( 'This will block sql in application parameters (e.g. %s, etc).' ), base64_decode( 'dW5pb24gc2VsZWN0LCBjb25jYXQoLCAvKiovLCAuLik=' ) );
				break;

			case 'block_wordpress_terms' :
				$sName = _wpsf__( 'WordPress Terms' );
				$sSummary = _wpsf__( 'Block WordPress Specific Terms' );
				$sDescription = _wpsf__( 'This will block WordPress specific terms in application parameters (wp_, user_login, etc.).' );
				break;

			case 'block_field_truncation' :
				$sName = _wpsf__( 'Field Truncation' );
				$sSummary = _wpsf__( 'Block Field Truncation Attacks' );
				$sDescription = _wpsf__( 'This will block field truncation attacks in application parameters.' );
				break;

			case 'block_php_code' :
				$sName = _wpsf__( 'PHP Code' );
				$sSummary = sprintf( _wpsf__( 'Block %s' ), _wpsf__( 'PHP Code Includes' ) );
				$sDescription = _wpsf__( 'This will block any data that appears to try and include PHP files.' )
								.'<br />'._wpsf__( 'Will probably block saving within the Plugin/Theme file editors.' );
				break;

			case 'block_exe_file_uploads' :
				$sName = _wpsf__( 'Exe File Uploads' );
				$sSummary = _wpsf__( 'Block Executable File Uploads' );
				$sDescription = _wpsf__( 'This will block executable file uploads (.php, .exe, etc.).' );
				break;

			case 'block_leading_schema' :
				$sName = _wpsf__( 'Leading Schemas' );
				$sSummary = _wpsf__( 'Block Leading Schemas (HTTPS / HTTP)' );
				$sDescription = _wpsf__( 'This will block leading schemas http:// and https:// in application parameters (off by default; may cause problems with other plugins).' );
				break;

			case 'block_aggressive' :
				$sName = _wpsf__( 'Aggressive Scan' );
				$sSummary = _wpsf__( 'Aggressively Block Data' );
				$sDescription = _wpsf__( 'Employs a set of aggressive rules to detect and block malicious data submitted to your site.' )
								.'<br />'.sprintf( '%s - %s', _wpsf__( 'Warning' ), _wpsf__( 'May cause an increase in false-positive firewall blocks.' ) );
				break;

			case 'block_response' :
				$sName = _wpsf__( 'Block Response' );
				$sSummary = _wpsf__( 'Choose how the firewall responds when it blocks a request' );
				$sDescription = _wpsf__( 'We recommend dying with a message so you know what might have occurred when the firewall blocks you' );
				break;

			case 'block_send_email' :
				$sName = _wpsf__( 'Send Email Report' );
				$sSummary = _wpsf__( 'When a visitor is blocked the firewall will send an email to the configured email address' );
				$sDescription = _wpsf__( 'Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host' );
				break;

			case 'page_params_whitelist' :
				$sName = _wpsf__( 'Whitelist Parameters' );
				$sSummary = _wpsf__( 'Detail pages and parameters that are whitelisted (ignored by the firewall)' );
				$sDescription = _wpsf__( 'This should be used with caution and you should only provide parameter names that you must have excluded' );
				break;

			case 'whitelist_admins' :
				$sName = sprintf( _wpsf__( 'Ignore %s' ), _wpsf__( 'Administrators' ) );
				$sSummary = sprintf( _wpsf__( 'Ignore %s' ), _wpsf__( 'Administrators' ) );
				$sDescription = _wpsf__( 'Authenticated administrator users will not be processed by the firewall rules.' );
				break;

			/** removed */
			case 'ignore_search_engines' :
				$sName = sprintf( _wpsf__( 'Ignore %s' ), _wpsf__( 'Search Engines' ) );
				$sSummary = _wpsf__( 'Ignore Search Engine Bot Traffic' );
				$sDescription = _wpsf__( 'The firewall will try to recognise search engine spiders/bots and not apply firewall rules to them.' );
				break;

			case 'text_firewalldie' :
				$sName = _wpsf__( 'Firewall Block Message' );
				$sSummary = _wpsf__( 'Message Displayed To Visitor When A Firewall Block Is Triggered' );
				$sDescription = _wpsf__( 'This is the message displayed to visitors that trigger the firewall.' );
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