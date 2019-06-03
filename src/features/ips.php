<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Ips extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

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
	public function isAutoBlackListEnabled() {
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
			$sMessage = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		else if ( $oProcessor->getDbHandler()->getQueryDeleter()->deleteById( $nId ) ) {
			$sMessage = __( 'IP address deleted', 'wp-simple-firewall' );
			$bSuccess = true;
		}
		else {
			$sMessage = __( "IP address wasn't deleted from the list", 'wp-simple-firewall' );
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	protected function ajaxExec_AddIp() {
		/** @var ICWP_WPSF_Processor_Ips $oProcessor */
		$oProcessor = $this->getProcessor();
		$oIpServ = Services::IP();

		$aFormParams = $this->getAjaxFormParams();

		$bSuccess = false;
		$sMessage = __( "IP address wasn't added to the list", 'wp-simple-firewall' );

		$sIp = preg_replace( '#[^/:\.a-f\d]#i', '', ( isset( $aFormParams[ 'ip' ] ) ? $aFormParams[ 'ip' ] : '' ) );
		$sList = isset( $aFormParams[ 'list' ] ) ? $aFormParams[ 'list' ] : '';

		$bAcceptableIp = $oIpServ->isValidIp( $sIp ) || $oIpServ->isValidIp4Range( $sIp );

		$bIsBlackList = $sList != self::LIST_MANUAL_WHITE;

		// TODO: Bring this IP verification out of here and make it more accessible
		if ( empty( $sIp ) ) {
			$sMessage = __( "IP address not provided", 'wp-simple-firewall' );
		}
		else if ( empty( $sList ) ) {
			$sMessage = __( "IP list not provided", 'wp-simple-firewall' );
		}
		else if ( !$bAcceptableIp ) {
			$sMessage = __( "IP address isn't either a valid IP or a CIDR range", 'wp-simple-firewall' );
		}
		else if ( $bIsBlackList && !$this->isPremium() ) {
			$sMessage = __( "Please upgrade to Pro if you'd like to add IPs to the black list manually.", 'wp-simple-firewall' );
		}
		else if ( $bIsBlackList && $oIpServ->isValidIp4Range( $sIp ) ) { // TODO
			$sMessage = __( "IP ranges aren't currently supported for blacklisting.", 'wp-simple-firewall' );
		}
		else if ( $bIsBlackList && $oIpServ->checkIp( $sIp, $oIpServ->getRequestIp() ) ) {
			$sMessage = __( "Manually black listing your current IP address is not supported.", 'wp-simple-firewall' );
		}
		else if ( $bIsBlackList && in_array( $sIp, $this->getReservedIps() ) ) {
			$sMessage = __( "This IP is reserved and can't be blacklisted.", 'wp-simple-firewall' );
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
				$sMessage = __( 'IP address added successfully', 'wp-simple-firewall' );
				$bSuccess = true;
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
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

		return [
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		];
	}

	protected function doExtraSubmitProcessing() {
		if ( !in_array( $this->getOpt( 'auto_expire' ), [ 'minute', 'hour', 'day', 'week' ] ) ) {
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
	public function isTrackOptImmediateBlock( $sOptionKey ) {
		return $this->isOpt( $sOptionKey, 'block' );
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
				if ( !$this->isAutoBlackListEnabled() ) {
					$aWarnings[] = sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( "IP blocking is turned-off because the offenses limit is set to 0.", 'wp-simple-firewall' ) );
				}
				break;

			case 'section_behaviours':
			case 'section_probes':
			case 'section_logins':
				if ( !$this->isAutoBlackListEnabled() ) {
					$aWarnings[] = __( "Since the offenses limit is set to 0, these options have no effect.", 'wp-simple-firewall' );
				}

				if ( $sSection == 'section_behaviours' && strlen( Services::Request()->getUserAgent() ) == 0 ) {
					$aWarnings[] = __( "Your User Agent appears to be empty. We recommend not turning on this option.", 'wp-simple-firewall' );
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
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'Repeated login attempts that fail will result in a complete ban of your IP Address.', 'wp-simple-firewall' )
				);
				break;

			case 'text_remainingtrans':
				$sText = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'You have %s remaining offenses(s) against this site and then your IP address will be completely blocked.', 'wp-simple-firewall' )
					.'<br/><strong>'.__( 'Seriously, stop repeating what you are doing or you will be locked out.', 'wp-simple-firewall' ).'</strong>'
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
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The IP Manager allows you to whitelist, blacklist and configure auto-blacklist rules.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'IP Manager', 'wp-simple-firewall' ) ) )
					.'<br />'.__( 'You should also carefully review the automatic black list settings.', 'wp-simple-firewall' )
				];
				break;

			case 'section_auto_black_list' :
				$sTitle = __( 'Auto IP Blocking Rules', 'wp-simple-firewall' );
				$sTitleShort = __( 'Auto Blocking Rules', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Automatic IP Black List system will block the IP addresses of naughty visitors after a specified number of offenses.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Automatic IP Black List', 'wp-simple-firewall' ) ) ),
					__( "Think of 'offenses' as just a counter for the number of times a visitor does something bad.", 'wp-simple-firewall' )
					.' '.sprintf( __( 'When the counter reaches the limit below (default: 10), %s will block that completely IP.', 'wp-simple-firewall' ), $sName )
				];
				break;

			case 'section_enable_plugin_feature_bottrap' :
				$sTitleShort = __( 'Bot-Trap', 'wp-simple-firewall' );
				$sTitle = __( 'Identify And Capture Bots Based On Their Site Activity', 'wp-simple-firewall' );
				$aSummary = [
					__( "A bot doesn't know what's real and what's not, so it probes many different avenues until it finds something it recognises.", 'wp-simple-firewall' ),
					__( "Bot-Trap monitors a set of typical bot behaviours to help identify probing bots.", 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enable as many mouse traps as possible.', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_logins':
				$sTitleShort = __( 'Login Bots', 'wp-simple-firewall' );
				$sTitle = __( 'Detect & Capture Login Bots', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Certain bots are designed to test your logins and this feature lets you decide how to handle them.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ),
						__( "Legitimate users may get their password wrong, so take care not to block this.", 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_probes':
				$sTitleShort = __( 'Probing Bots', 'wp-simple-firewall' );
				$sTitle = __( 'Detect & Capture Probing Bots', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Bots are designed to probe and this feature is dedicated to detecting probing bots.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_behaviours':
				$sTitleShort = __( 'Bot Behaviours', 'wp-simple-firewall' );
				$sTitle = __( 'Detect Behaviours Common To Bots', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Summary', 'wp-simple-firewall' ),
						__( "Detect characteristics and behaviour commonly associated with illegitimate bots.", 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Enable as many options as possible.", 'wp-simple-firewall' ) ),
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
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'transgression_limit' :
				$sName = __( 'Offense Limit', 'wp-simple-firewall' );
				$sSummary = __( 'Visitor IP address will be Black Listed after X bad actions on your site', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'A black mark is set against an IP address each time a visitor trips the defenses of the %s plugin.', 'wp-simple-firewall' ), $sPlugName )
								.'<br />'.__( 'When the number of these offenses exceeds the limit, they are automatically blocked from accessing the site.', 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'Set this to "0" to turn off the %s feature.', 'wp-simple-firewall' ), __( 'Automatic IP Black List', 'wp-simple-firewall' ) );
				break;

			case 'auto_expire' :
				$sName = __( 'Auto Block Expiration', 'wp-simple-firewall' );
				$sSummary = __( 'After 1 "X" a black listed IP will be removed from the black list', 'wp-simple-firewall' );
				$sDescription = __( 'Permanent and lengthy IP Black Lists are harmful to performance.', 'wp-simple-firewall' )
								.'<br />'.__( 'You should allow IP addresses on the black list to be eventually removed over time.', 'wp-simple-firewall' )
								.'<br />'.__( 'Shorter IP black lists are more efficient and a more intelligent use of an IP-based blocking system.', 'wp-simple-firewall' );
				break;

			case 'user_auto_recover' :
				$sName = __( 'User Auto Unblock', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Visitors To Unblock Their IP', 'wp-simple-firewall' );
				$sDescription = __( 'Allow visitors blocked by the plugin to automatically unblock themselves.', 'wp-simple-firewall' );
				break;

			case 'text_loginfailed' :
				$sName = __( 'Login Failed', 'wp-simple-firewall' );
				$sSummary = __( 'Visitor Triggers The IP Offense System Through A Failed Login', 'wp-simple-firewall' );
				$sDescription = __( 'This message is displayed if the visitor fails a login attempt.', 'wp-simple-firewall' );
				break;

			case 'text_remainingtrans' :
				$sName = __( 'Remaining Offenses', 'wp-simple-firewall' );
				$sSummary = __( 'Visitor Triggers The IP Offenses System Through A Firewall Block', 'wp-simple-firewall' );
				$sDescription = __( 'This message is displayed if the visitor triggered the IP Offense system and reports how many offenses remain before being blocked.', 'wp-simple-firewall' );
				break;

			case 'track_404' :
				$sName = __( '404 Detect', 'wp-simple-firewall' );
				$sSummary = __( 'Identify A Bot When It Hits A 404', 'wp-simple-firewall' );
				$sDescription = __( "Detect when a visitor tries to load a non-existent page.", 'wp-simple-firewall' )
								.'<br/>'.__( "Care should be taken to ensure you don't have legitimate links on your site that are 404s.", 'wp-simple-firewall' );
				break;

			case 'track_xmlrpc' :
				$sName = __( 'XML-RPC Access', 'wp-simple-firewall' );
				$sSummary = __( 'Identify A Bot When It Accesses XML-RPC', 'wp-simple-firewall' );
				$sDescription = __( "If you don't use XML-RPC, there's no reason anything should be accessing it.", 'wp-simple-firewall' )
								.'<br/>'.__( "Be careful the ensure you don't block legitimate XML-RPC traffic if your site needs it.", 'wp-simple-firewall' )
								.'<br/>'.__( "We recommend logging here in-case of blocking valid request unless you're sure.", 'wp-simple-firewall' );
				break;

			case 'track_linkcheese' :
				$sName = __( 'Link Cheese', 'wp-simple-firewall' );
				$sSummary = __( 'Tempt A Bot With A Fake Link To Follow', 'wp-simple-firewall' );
				$sDescription = __( "Detect a bot when it follows a fake 'no-follow' link.", 'wp-simple-firewall' )
								.'<br/>'.__( "This works because legitimate web crawlers respect 'robots.txt' and 'nofollow' directives.", 'wp-simple-firewall' );
				break;

			case 'track_logininvalid' :
				$sName = __( 'Invalid Usernames', 'wp-simple-firewall' );
				$sSummary = __( "Detect Attempted Logins With Usernames That Don't Exist", 'wp-simple-firewall' );
				$sDescription = __( "Identify a Bot when it tries to login with a non-existent username.", 'wp-simple-firewall' )
								.'<br/>'.__( "This includes the default 'admin' if you've removed that account.", 'wp-simple-firewall' );
				break;

			case 'track_loginfailed' :
				$sName = __( 'Failed Login', 'wp-simple-firewall' );
				$sSummary = __( 'Detect Failed Login Attempts Using Valid Usernames', 'wp-simple-firewall' );
				$sDescription = __( "Penalise a visitor when they try to login using a valid username, but it fails.", 'wp-simple-firewall' );
				break;

			case 'track_fakewebcrawler' :
				$sName = __( 'Fake Web Crawler', 'wp-simple-firewall' );
				$sSummary = __( 'Detect Fake Search Engine Crawlers', 'wp-simple-firewall' );
				$sDescription = __( "Identify a Bot when it presents as an official web crawler, but analysis shows it's fake.", 'wp-simple-firewall' );
				break;

			case 'track_useragent' :
				$sName = __( 'Empty User Agents', 'wp-simple-firewall' );
				$sSummary = __( 'Detect Requests With Empty User Agents', 'wp-simple-firewall' );
				$sDescription = __( "Identify a bot when the user agent is not provided.", 'wp-simple-firewall' )
								.'<br />'.sprintf( '%s: <code>%s</code>',
						__( 'Your user agent is', 'wp-simple-firewall' ), Services::Request()->getUserAgent() );
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
					sprintf( __( 'Sorry, the %s feature may not be disabled while there are IP addresses in the White List', 'wp-simple-firewall' ), $this->getMainFeatureName() )
				);
			}
		}
	}

	/**
	 * @return Shield\Modules\IPs\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\IPs\Strings();
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

	/**
	 * @return bool
	 * @deprecated
	 */
	public function isAutoBlackListFeatureEnabled() {
		return $this->isAutoBlackListEnabled();
	}
}