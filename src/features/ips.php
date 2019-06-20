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
		$sConstant = strtoupper( $this->getOpt( 'auto_expire' ).'_IN_SECONDS' );
		return defined( $sConstant ) ? constant( $sConstant ) : ( DAY_IN_SECONDS*30 );
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

		$bSuccess = false;
		$nId = Services::Request()->post( 'rid', -1 );
		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		else if ( $this->getDbHandler()->getQueryDeleter()->deleteById( $nId ) ) {
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
						$oUpd = $this->getDbHandler()->getQueryUpdater();
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
			->setDbHandler( $this->getDbHandler() );

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
		return [
			Services::Request()->getServerAddress(),
			$this->getCon()->getModule_Plugin()->getMyServerIp()
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
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function onPluginShutdown() {
		if ( !$this->getCon()->isPluginDeleting() ) {
			$this->addFilterIpsToWhiteList();
		}
		parent::onPluginShutdown(); //save
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
	 * @return Shield\Databases\IPs\Handler
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\IPs\Handler();
	}

	/**
	 * @return Shield\Modules\IPs\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\IPs\Options();
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