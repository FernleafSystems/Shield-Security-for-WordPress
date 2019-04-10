<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Ips extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

	protected function updateHandler() {
		if ( $this->isOpt( 'track_404', 'assign-transgression' ) ) {
			$this->setOpt( 'track_404', 'transgression-single' ); // fix for older options values
		}
	}

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		$oIp = Services::IP();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) && parent::isReadyToExecute();
	}

	/**
	 * @return string
	 */
	public function getOptTransgressionLimit() {
		return $this->getOpt( 'transgression_limit' );
	}

	/**
	 * @return int
	 */
	public function getAutoExpireTime() {
		return constant( strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' ) );
	}

	/**
	 * @return bool
	 */
	public function isAutoBlackListFeatureEnabled() {
		return ( $this->getOptTransgressionLimit() > 0 );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'ip_insert':
					$aAjaxResponse = $this->ajaxExec_AddIp();
					break;

				case 'ip_delete':
					$aAjaxResponse = $this->ajaxExec_IpDelete();
					break;

				case 'render_table_ip':
					$aAjaxResponse = $this->ajaxExec_BuildTableIps();
					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	protected function ajaxExec_IpDelete() {
		/** @var ICWP_WPSF_Processor_Ips $oProcessor */
		$oProcessor = $this->getProcessor();

		$bSuccess = false;
		$nId = Services::Request()->post( 'rid', -1 );
		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = _wpsf__( 'Invalid entry selected' );
		}
		else if ( $oProcessor->getDbHandler()->getQueryDeleter()->deleteById( $nId ) ) {
			$sMessage = _wpsf__( 'IP address deleted' );
			$bSuccess = true;
		}
		else {
			$sMessage = _wpsf__( "IP address wasn't deleted from the list" );
		}

		return array(
			'success' => $bSuccess,
			'message' => $sMessage,
		);
	}

	protected function ajaxExec_AddIp() {
		/** @var ICWP_WPSF_Processor_Ips $oProcessor */
		$oProcessor = $this->getProcessor();
		$oIpServ = Services::IP();

		$aFormParams = $this->getAjaxFormParams();

		$bSuccess = false;
		$sMessage = _wpsf__( "IP address wasn't added to the list" );

		$sIp = preg_replace( '#[^/:\.a-f\d]#i', '', ( isset( $aFormParams[ 'ip' ] ) ? $aFormParams[ 'ip' ] : '' ) );
		$sList = isset( $aFormParams[ 'list' ] ) ? $aFormParams[ 'list' ] : '';

		$bAcceptableIp = $oIpServ->isValidIp( $sIp ) || $oIpServ->isValidIp4Range( $sIp );

		$bIsBlackList = $sList != self::LIST_MANUAL_WHITE;

		// TODO: Bring this IP verification out of here and make it more accessible
		if ( empty( $sIp ) ) {
			$sMessage = _wpsf__( "IP address not provided" );
		}
		else if ( empty( $sList ) ) {
			$sMessage = _wpsf__( "IP list not provided" );
		}
		else if ( !$bAcceptableIp ) {
			$sMessage = _wpsf__( "IP address isn't either a valid IP or a CIDR range" );
		}
		else if ( $bIsBlackList && !$this->isPremium() ) {
			$sMessage = _wpsf__( "Please upgrade to Pro if you'd like to add IPs to the black list manually." );
		}
		else if ( $bIsBlackList && $oIpServ->isValidIp4Range( $sIp ) ) { // TODO
			$sMessage = _wpsf__( "IP ranges aren't currently supported for blacklisting." );
		}
		else if ( $bIsBlackList && $oIpServ->checkIp( $sIp, $oIpServ->getRequestIp() ) ) {
			$sMessage = _wpsf__( "Manually black listing your current IP address is not supported." );
		}
		else if ( $bIsBlackList && in_array( $sIp, $this->getReservedIps() ) ) {
			$sMessage = _wpsf__( "This IP is reserved and can't be blacklisted." );
		}
		else {
			$sLabel = isset( $aFormParams[ 'label' ] ) ? $aFormParams[ 'label' ] : '';
			switch ( $sList ) {

				case self::LIST_MANUAL_WHITE:
					$oIp = $oProcessor->addIpToWhiteList( $sIp, $sLabel );
					break;

				case self::LIST_MANUAL_BLACK:
					$oIp = $oProcessor->addIpToBlackList( $sIp, $sLabel );
					if ( !empty( $oIp ) ) {
						/** @var Shield\Databases\IPs\Update $oUpd */
						$oUpd = $oProcessor->getDbHandler()->getQueryUpdater();
						$oUpd->updateTransgressions( $oIp, $this->getOptTransgressionLimit() );
					}
					break;

				default:
					$oIp = null;
					break;
			}

			if ( !empty( $oIp ) ) {
				$sMessage = _wpsf__( 'IP address added successfully' );
				$bSuccess = true;
			}
		}

		return array(
			'success' => $bSuccess,
			'message' => $sMessage,
		);
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_BuildTableIps() {
		/** @var ICWP_WPSF_Processor_Ips $oPro */
		$oPro = $this->getProcessor();

		// First Clean
		$oPro->cleanupDatabase();

		$oTableBuilder = ( new Shield\Tables\Build\Ip() )
			->setMod( $this )
			->setDbHandler( $oPro->getDbHandler() );

		return array(
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		);
	}

	protected function doExtraSubmitProcessing() {
		if ( !in_array( $this->getOpt( 'auto_expire' ), array( 'minute', 'hour', 'day', 'week' ) ) ) {
			$this->getOptionsVo()->resetOptToDefault( 'auto_expire' );
		}

		$nLimit = $this->getOptTransgressionLimit();
		if ( !is_int( $nLimit ) || $nLimit < 0 ) {
			$this->getOptionsVo()->resetOptToDefault( 'transgression_limit' );
		}
	}

	/**
	 * IP addresses that should never be put on the black list.
	 * @return string[]
	 */
	public function getReservedIps() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oPluginMod */
		$oPluginMod = $this->getCon()->getModule( 'plugin' );
		return [
			Services::Request()->getServerAddress(),
			$oPluginMod->getMyServerIp()
		];
	}

	/**
	 * @return array
	 */
	public function getAutoUnblockIps() {
		$aIps = $this->getOpt( 'autounblock_ips', [] );
		return is_array( $aIps ) ? $aIps : [];
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	public function getCanIpRequestAutoUnblock( $sIp ) {
		$aExistingIps = $this->getAutoUnblockIps();
		return !array_key_exists( $sIp, $aExistingIps )
			   || ( Services::Request()->ts() - $aExistingIps[ $sIp ] > DAY_IN_SECONDS );
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function updateIpRequestAutoUnblockTs( $sIp ) {
		$aExistingIps = $this->getAutoUnblockIps();
		$aExistingIps[ $sIp ] = Services::Request()->ts();
		return $this->setAutoUnblockIps( $aExistingIps );
	}

	/**
	 * @param array $aIps
	 * @return $this
	 */
	public function setAutoUnblockIps( $aIps ) {
		return $this->setOpt( 'autounblock_ips', $aIps );
	}

	/**
	 * @return bool
	 */
	public function isEnabledAutoUserRecover() {
		return !$this->isOpt( 'user_auto_recover', 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrack404() {
		return $this->isSelectOptionEnabled( 'track_404' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackFakeWebCrawler() {
		return $this->isSelectOptionEnabled( 'track_fakewebcrawler' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackLoginInvalid() {
		return $this->isSelectOptionEnabled( 'track_logininvalid' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackLoginFailed() {
		return $this->isSelectOptionEnabled( 'track_loginfailed' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackLinkCheese() {
		return $this->isSelectOptionEnabled( 'track_linkcheese' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledTrackXmlRpc() {
		return $this->isSelectOptionEnabled( 'track_xmlrpc' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	public function isTrackOptTransgression( $sOptionKey ) {
		return strpos( $this->getOpt( $sOptionKey ), 'transgression' ) !== false;
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	public function isTrackOptDoubleTransgression( $sOptionKey ) {
		return $this->isOpt( $sOptionKey, 'transgression-double' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	public function isTrackOptLogOnly( $sOptionKey ) {
		return $this->isOpt( $sOptionKey, 'log' );
	}

	/**
	 * @param string $sOptionKey
	 * @return bool
	 */
	protected function isSelectOptionEnabled( $sOptionKey ) {
		$bOptPrem = $this->getOptionsVo()->isOptPremium( $sOptionKey );
		return ( !$bOptPrem || $this->getCon()->isPremiumActive() ) && !$this->isOpt( $sOptionKey, 'disabled' );
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		switch ( $sSection ) {

			case 'section_auto_black_list':
				if ( !$this->isAutoBlackListFeatureEnabled() ) {
					$aWarnings[] = sprintf( '%s: %s', _wpsf__( 'Note' ), _wpsf__( "IP blocking is turned-off because the transgressions limit is set to 0." ) );
				}
				break;

			case 'section_behaviours':
			case 'section_probes':
			case 'section_logins':
				if ( !$this->isAutoBlackListFeatureEnabled() ) {
					$aWarnings[] = _wpsf__( "Since the transgressions limit is set to 0, these options have no effect." );
				}

				if ( $sSection == 'section_behaviours' && strlen( Services::Request()->getUserAgent() ) == 0 ) {
					$aWarnings[] = _wpsf__( "Your User Agent appears to be empty. We recommend not turning on this option." );
				}
				break;
		}

		return $aWarnings;
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {

			case 'text_loginfailed':
				$sText = sprintf( '%s: %s',
					_wpsf__( 'Warning' ),
					_wpsf__( 'Repeated login attempts that fail will result in a complete ban of your IP Address.' )
				);
				break;

			case 'text_remainingtrans':
				$sText = sprintf( '%s: %s',
					_wpsf__( 'Warning' ),
					_wpsf__( 'You have %s remaining transgression(s) against this site and then your IP address will be completely blocked.' )
					.'<br/><strong>'._wpsf__( 'Seriously, stop repeating what you are doing or you will be locked out.' ).'</strong>'
				);
				break;

			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {
		$sName = $this->getCon()->getHumanName();
		switch ( $aOptionsParams[ 'slug' ] ) {

			case 'section_enable_plugin_feature_ips' :
				$sTitle = sprintf( _wpsf__( 'Enable Module: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'IP Manager' ) ) )
					.'<br />'._wpsf__( 'You should also carefully review the automatic black list settings.' )
				);
				$sTitleShort = sprintf( _wpsf__( '%s/%s Module' ), _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_auto_black_list' :
				$sTitle = _wpsf__( 'Auto IP Blocking Rules' );
				$sTitleShort = _wpsf__( 'Auto IP Blocking Rules' );
				$aSummary = array(
					sprintf( '%s - %s', _wpsf__( 'Purpose' ), _wpsf__( 'The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of transgressions.' ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Automatic IP Black List' ) ) ),
					_wpsf__( "Think of 'transgressions' as just a counter for the number of times a visitor does something bad." )
					.' '.sprintf( _wpsf__( 'When the counter reaches the limit below (default: 10), %s will block that completely IP.' ), $sName )
				);
				break;

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
				$sTitle = _wpsf__( 'Detect & Capture Login Bots' );
				$sTitleShort = _wpsf__( 'Detect Login Bots' );
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
				$sTitle = _wpsf__( 'Detect & Capture Probing Bots' );
				$sTitleShort = _wpsf__( 'Detect Probing Bots' );
				$aSummary = [
					sprintf( '%s - %s', _wpsf__( 'Summary' ),
						_wpsf__( "Bots are designed to probe and this feature is dedicated to detecting probing bots." ) ),
					sprintf( '%s - %s', _wpsf__( 'Recommendation' ),
						_wpsf__( "Enable as many options as possible." ) ),
				];
				break;

			case 'section_behaviours':
				$sTitle = _wpsf__( 'Detect Behaviours Common To Bots' );
				$sTitleShort = _wpsf__( 'Detect Bot Behaviours' );
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

		$sPlugName = $this->getCon()->getHumanName();
		switch ( $aOptionsParams[ 'key' ] ) {

			case 'enable_ips' :
				$sName = sprintf( _wpsf__( 'Enable %s Module' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Module' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Un-Checking this option will completely disable the %s module.' ), $this->getMainFeatureName() );
				break;

			case 'transgression_limit' :
				$sName = _wpsf__( 'Transgression Limit' );
				$sSummary = _wpsf__( 'Visitor IP address will be Black Listed after X bad actions on your site' );
				$sDescription = sprintf( _wpsf__( 'A black mark is set against an IP address each time a visitor trips the defenses of the %s plugin.' ), $sPlugName )
								.'<br />'._wpsf__( 'When the number of these transgressions exceeds specified limit, they are automatically blocked from accessing the site.' )
								.'<br />'.sprintf( _wpsf__( 'Set this to "0" to turn off the %s feature.' ), _wpsf__( 'Automatic IP Black List' ) );
				break;

			case 'auto_expire' :
				$sName = _wpsf__( 'Auto Block Expiration' );
				$sSummary = _wpsf__( 'After 1 "X" a black listed IP will be removed from the black list' );
				$sDescription = _wpsf__( 'Permanent and lengthy IP Black Lists are harmful to performance.' )
								.'<br />'._wpsf__( 'You should allow IP addresses on the black list to be eventually removed over time.' )
								.'<br />'._wpsf__( 'Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system.' );
				break;

			case 'user_auto_recover' :
				$sName = _wpsf__( 'User Auto Unblock' );
				$sSummary = _wpsf__( 'Allow Visitors To Unblock Their IP' );
				$sDescription = _wpsf__( 'Allow visitors blocked by the plugin to automatically unblock themselves.' );
				break;

			case 'text_loginfailed' :
				$sName = _wpsf__( 'Login Failed' );
				$sSummary = _wpsf__( 'Visitor Triggers The IP Transgression System Through A Failed Login' );
				$sDescription = _wpsf__( 'This message is displayed if the visitor fails a login attempt.' );
				break;

			case 'text_remainingtrans' :
				$sName = _wpsf__( 'Remaining Transgressions' );
				$sSummary = _wpsf__( 'Visitor Triggers The IP Transgression System Through A Firewall Block' );
				$sDescription = _wpsf__( 'This message is displayed if the visitor triggered the IP Transgression system and reports how many transgressions remain before being blocked.' );
				break;

			case 'track_404' :
				$sName = _wpsf__( '404 Detect' );
				$sSummary = _wpsf__( 'Identify A Bot When It Hits A 404' );
				$sDescription = _wpsf__( "Detect when a visitor tries to load a non-existent page." )
								.'<br/>'._wpsf__( "Care should be taken to ensure you don't have legitimate links on your site that are 404s." );
				break;

			case 'track_xmlrpc' :
				$sName = _wpsf__( 'XML-RPC Access' );
				$sSummary = _wpsf__( 'Identify A Bot When It Accesses XML-RPC' );
				$sDescription = _wpsf__( "If you don't use XML-RPC, there's no reason anything should be accessing it." )
								.'<br/>'._wpsf__( "Be careful the ensure you don't block legitimate XML-RPC traffic if your site needs it." )
								.'<br/>'._wpsf__( "We recommend transgressions here in-case of blocking valid request unless you're sure." );
				break;

			case 'track_linkcheese' :
				$sName = _wpsf__( 'Link Cheese' );
				$sSummary = _wpsf__( 'Tempt A Bot With A Fake Link To Follow' );
				$sDescription = _wpsf__( "Detect a bot when it follows a fake 'no-follow' link." )
								.'<br/>'._wpsf__( "This works because legitimate web crawlers respect 'robots.txt' and 'nofollow' directives." );
				break;

			case 'track_logininvalid' :
				$sName = _wpsf__( 'Invalid Usernames' );
				$sSummary = _wpsf__( "Detect Attempted Logins With Usernames That Don't Exist" );
				$sDescription = _wpsf__( "Identify a Bot when it tries to login with a non-existent username." )
								.'<br/>'._wpsf__( "This includes the default 'admin' if you've removed that account." );
				break;

			case 'track_loginfailed' :
				$sName = _wpsf__( 'Failed Login' );
				$sSummary = _wpsf__( 'Detect Failed Login Attempts Using Valid Usernames' );
				$sDescription = _wpsf__( "Penalise a visitor when they try to login using a valid username, but it fails." );
				break;

			case 'track_fakewebcrawler' :
				$sName = _wpsf__( 'Fake Web Crawler' );
				$sSummary = _wpsf__( 'Detect Fake Search Engine Crawlers' );
				$sDescription = _wpsf__( "Identify a Bot when it presents as an official web crawler, but analysis shows it's fake." );
				break;

			case 'track_useragent' :
				$sName = _wpsf__( 'Empty User Agents' );
				$sSummary = _wpsf__( 'Detect Requests With Empty User Agents' );
				$sDescription = _wpsf__( "Identify a bot when the user agent is not provided." )
								.'<br />'.sprintf( '%s: <code>%s</code>',
						_wpsf__( 'Your user agent is' ), Services::Request()->getUserAgent() );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $aOptionsParams[ 'key' ] ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		if ( !$this->getCon()->isPluginDeleting() ) {
			$this->addFilterIpsToWhiteList();
		}
		parent::action_doFeatureShutdown(); //save
	}

	/**
	 */
	protected function addFilterIpsToWhiteList() {
		$aIps = [];
		$oSp = $this->loadServiceProviders();

		if ( function_exists( 'mwp_init' ) ) {
			foreach ( array_flip( $oSp->getIps_ManageWp() ) as $sIp => $n ) {
				$aIps[ $sIp ] = 'ManageWP';
			}
		}

		if ( class_exists( 'ICWP_Plugin' ) ) {
			foreach ( array_flip( $oSp->getIps_iControlWP( true ) ) as $sIp => $n ) {
				$aIps[ $sIp ] = 'iControlWP';
			}
		}

		$aIps = apply_filters( 'icwp_simple_firewall_whitelist_ips', $aIps );

		if ( !empty( $aIps ) && is_array( $aIps ) ) {
			/** @var ICWP_WPSF_Processor_Ips $oPro */
			$oPro = $this->getProcessor();

			$aWhiteIps = $oPro->getWhitelistIps();
			foreach ( $aIps as $sIP => $sLabel ) {
				if ( !in_array( $sIP, $aWhiteIps ) ) {
					$oPro->addIpToWhiteList( $sIP, $sLabel );
				}
			}
		}
	}

	protected function ensureFeatureEnabled() {
		// we prevent disabling of this feature if the white list isn't empty
		if ( !$this->isModuleEnabled() ) {
			/** @var ICWP_WPSF_Processor_Ips $oProcessor */
			$oProcessor = $this->getProcessor();
			if ( count( $oProcessor->getWhitelistIpsData() ) > 0 ) {
				$this->setIsMainFeatureEnabled( true );
				$this->setFlashAdminNotice(
					sprintf( _wpsf__( 'Sorry, the %s feature may not be disabled while there are IP addresses in the White List' ), $this->getMainFeatureName() )
				);
			}
		}
	}

	/**
	 * @return string
	 * @deprecated 7.3
	 */
	public function getOptTracking404() {
		return $this->getOpt( 'track_404' );
	}

	/**
	 * @return bool
	 * @deprecated 7.3
	 */
	public function is404Tracking() {
		return !$this->isOpt( 'track_404', 'disabled' );
	}
}