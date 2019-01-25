<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

class ICWP_WPSF_Processor_Ips extends ICWP_WPSF_BaseDbProcessor {

	/* Unused */
	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

	/**
	 * @var bool
	 */
	protected $bVisitorIsWhitelisted;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Ips $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Ips $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'ip_lists_table_name' ) );
	}

	/**
	 */
	public function run() {
		if ( !$this->isReadyToRun() ) {
			return;
		}

		$this->processBlacklist();

		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isAutoBlackListFeatureEnabled() ) {
			add_filter( $oFO->prefix( 'firewall_die_message' ), array( $this, 'fAugmentFirewallDieMessage' ) );
			add_action( $oFO->prefix( 'pre_plugin_shutdown' ), array( $this, 'action_blackMarkIp' ) );
			add_action( 'wp_login_failed', array( $this, 'setIpTransgressed' ), 10, 0 );
		}

		add_filter( 'authenticate', array( $this, 'addLoginFailedWarningMessage' ), 10000, 1 );
		add_action( 'template_redirect', array( $this, 'doTrack404' ) );
	}

	public function doTrack404() {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		if ( $oFO->is404Tracking() && is_404() && !$oFO->isVerifiedBot() ) {
			if ( $oFO->getOptTracking404() == 'assign-transgression' ) {
				$this->setIpTransgressed(); // We now black mark this IP
			}
			$this->addToAuditEntry(
				sprintf( _wpsf__( '404 detected at "%s"' ), $this->loadRequest()->getPath() ),
				2, 'request_tracking_404'
			);
		}
	}

	/**
	 * @param WP_User|WP_Error $oUserOrError
	 * @return WP_User|WP_Error
	 */
	public function addLoginFailedWarningMessage( $oUserOrError ) {
		if ( $this->loadWp()->isRequestUserLogin() && is_wp_error( $oUserOrError ) ) {
			$oUserOrError->add(
				$this->getMod()->prefix( 'transgression-warning' ),
				$this->getMod()->getTextOpt( 'text_loginfailed' )
			);
		}
		return $oUserOrError;
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 */
	public function addNotice_visitor_whitelisted( $aNoticeAttributes ) {
		$oCon = $this->getCon();

		if ( $oCon->getIsPage_PluginAdmin() && $this->isCurrentIpWhitelisted() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'title'             => sprintf( _wpsf__( '%s is ignoring you' ), $oCon->getHumanName() ),
					'your_ip'           => sprintf( _wpsf__( 'Your IP address is: %s' ), $this->ip() ),
					'notice_message'    => _wpsf__( 'Your IP address is whitelisted and NO features you activate apply to you.' ),
					'including_message' => _wpsf__( 'Including the hiding the WP Login page.' )
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @return array
	 */
	public function getAllValidLists() {
		return array(
			ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK,
			ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE,
			ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK
		);
	}

	/**
	 * @param string $sIp
	 * @return boolean
	 */
	protected function isValidIpOrRange( $sIp ) {
		$oIP = $this->loadIpService();
		return $oIP->isValidIp_PublicRemote( $sIp ) || $oIP->isValidIpRange( $sIp );
	}

	/**
	 * @param array $aMessages
	 * @return array
	 */
	public function fAugmentFirewallDieMessage( $aMessages ) {
		if ( !is_array( $aMessages ) ) {
			$aMessages = array();
		}
		$aMessages[] = sprintf( '<p>%s</p>', $this->getTextOfRemainingTransgressions() );
		return $aMessages;
	}

	/**
	 * @param WP_User|WP_Error $oUserOrError
	 * @param string           $sUsername
	 * @return WP_User|WP_Error
	 */
	public function verifyIfAuthenticationValid( $oUserOrError, $sUsername ) {
		// Don't concern yourself if visitor is whitelisted
		if ( $this->isCurrentIpWhitelisted() ) {
			return $oUserOrError;
		}

		$bBlackMark = false;
		$oWp = $this->loadWp();
		if ( $oWp->isRequestUserLogin() ) {

			// If there's an attempt to login with a non-existent username
			if ( !empty( $sUsername ) && !in_array( $sUsername, $oWp->getAllUserLoginUsernames() ) ) {
				$bBlackMark = true;
			}
			else {
				// If the login failed.
				$bUserLoginSuccess = is_object( $oUserOrError ) && ( $oUserOrError instanceof WP_User );
				if ( !$bUserLoginSuccess ) {
					$bBlackMark = true;
				}
			}
		}

		if ( $bBlackMark ) {
			$this->setIpTransgressed(); // We now black mark this IP

			if ( !is_wp_error( $oUserOrError ) ) {
				$oUserOrError = new WP_Error();
			}
			$oUserOrError->add( 'wpsf-autoblacklist', $this->getTextOfRemainingTransgressions() );
		}

		return $oUserOrError;
	}

	/**
	 * @return string
	 */
	private function getTextOfRemainingTransgressions() {
		return sprintf(
			$this->getMod()->getTextOpt( 'text_remainingtrans' ),
			$this->getRemainingTransgressions() - 1 // we take one off because it hasn't been incremented at this stage
		);
	}

	/**
	 * @param string $sIp
	 * @return string
	 */
	protected function getRemainingTransgressions( $sIp = '' ) {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		if ( empty( $sIp ) ) {
			$sIp = $this->ip();
		}
		return $oFO->getOptTransgressionLimit() - $this->getTransgressions( $sIp );
	}

	/**
	 * The auto black list isn't a simple lookup, but rather has an auto expiration and a transgression count
	 * @param string $sIp
	 * @return int
	 */
	private function getTransgressions( $sIp ) {
		$oBlackIp = $this->getAutoBlackListIp( $sIp );
		return ( $oBlackIp instanceof IPs\EntryVO ) ? $oBlackIp->getTransgressions() : 0;
	}

	protected function processBlacklist() {
		if ( $this->isCurrentIpWhitelisted() ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		$sIp = $this->ip();
		$bKill = false;

		// TODO: *Maybe* Have a manual black list process first.

		// now try auto black list
		if ( !$bKill && $oFO->isAutoBlackListFeatureEnabled() ) {
			$bKill = $this->isIpToBeBlocked( $sIp );
		}

		if ( $bKill ) {
			$sAuditMessage = sprintf( _wpsf__( 'Visitor was found to be on the Black List with IP address "%s" and their connection was killed.' ), $sIp );
			$this->setIfLogRequest( false )// don't log traffic from killed requests
				 ->doStatIncrement( 'ip.connection.killed' )
				 ->addToAuditEntry( $sAuditMessage, 3, 'black_list_connection_killed' );
			$oFO->setOptInsightsAt( 'last_ip_block_at' );

			/** @var IPs\Update $oUp */
			$oUp = $this->getDbHandler()->getQueryUpdater();
			$oUp->updateLastAccessAt( $this->getAutoBlackListIp( $sIp ) );

			$this->loadWp()
				 ->wpDie(
					 '<h3>'.sprintf( _wpsf__( 'You have been black listed by the %s plugin.' ),
						 '<a href="https://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'.$this->getCon()
																											 ->getHumanName().'</a>'
					 ).'</h3>'
					 .'<br />'.sprintf( _wpsf__( 'You tripped the security plugin defenses a total of %s times making you a suspect.' ), $oFO->getOptTransgressionLimit() )
					 .'<br />'.sprintf( _wpsf__( 'If you believe this to be in error, please contact the site owner.' ) )
					 .'<p>'.sprintf( _wpsf__( 'Time remaining until you are automatically removed from the black list: %s minute(s)' ), floor( $oFO->getAutoExpireTime()/60 ) )
					 .'<br />'._wpsf__( 'If you attempt to access the site within this period the counter will be reset.' )
					 .'</p>'
				 );
		}
	}

	/**
	 */
	public function action_blackMarkIp() {
		$this->blackMarkCurrentVisitor();
	}

	/**
	 */
	protected function blackMarkCurrentVisitor() {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();

		if ( $this->getIfIpTransgressed() && !$oFO->isVerifiedBot() && !$this->isCurrentIpWhitelisted() ) {

			// Never black mark IPs that are on the whitelist
			$bCanBlackMark = $oFO->isAutoBlackListFeatureEnabled()
							 && !$this->getCon()->isPluginDeleting();

			if ( $bCanBlackMark ) {
				$this->processIpBlackMark( $this->ip() );
			}
		}
	}

	/**
	 * @param string $sIp
	 */
	private function processIpBlackMark( $sIp ) {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		$oFO->setOptInsightsAt( 'last_transgression_at' );
		$this->doStatIncrement( 'ip.transgression.incremented' );

		$oBlackIp = $this->getAutoBlackListIp( $sIp );
		if ( $oBlackIp instanceof IPs\EntryVO ) {

			/** @var IPs\Update $oUp */
			$oUp = $this->getDbHandler()->getQueryUpdater();
			$oUp->incrementTransgressions( $oBlackIp );

			$sAuditMessage = sprintf(
				_wpsf__( 'Auto Black List transgression counter was incremented from %s to %s.' ),
				$oBlackIp->getTransgressions() - 1,
				$oBlackIp->getTransgressions()
			);
			$this->addToAuditEntry( $sAuditMessage, 2, 'transgression_counter_increment' );
		}
		else {
			$this->addIpToAutoBlackList( $sIp );

			$sAuditMessage = sprintf(
				_wpsf__( 'Auto Black List transgression counter was started for visitor.' ),
				$sIp
			);
			$this->addToAuditEntry( $sAuditMessage, 2, 'transgression_counter_started' );
		}
	}

	/**
	 * @return bool
	 */
	public function isCurrentIpWhitelisted() {
		if ( !isset( $this->bVisitorIsWhitelisted ) ) {
			$this->bVisitorIsWhitelisted = $this->isIpOnWhiteList( $this->ip() );
		}
		return $this->bVisitorIsWhitelisted;
	}

	/**
	 * @return IPs\EntryVO[]
	 */
	public function getAutoBlacklistIpsData() {
		/** @var IPs\Select $oSelect */
		$oSelect = $this->getDbHandler()->getQuerySelector();
		return $oSelect->allFromList( ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK );
	}

	/**
	 * @return string[]
	 */
	public function getAutoBlacklistIps() {
		$aIps = array();
		foreach ( $this->getAutoBlacklistIpsData() as $oIp ) {
			$aIps[] = $oIp->ip;
		}
		return $aIps;
	}

	/**
	 * @return IPs\EntryVO[]
	 */
	public function getWhitelistIpsData() {
		/** @var IPs\Select $oSelect */
		$oSelect = $this->getDbHandler()->getQuerySelector();
		return $oSelect->allFromList( ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE );
	}

	/**
	 * @return string[]
	 */
	public function getWhitelistIps() {
		$aIps = array();
		foreach ( $this->getWhitelistIpsData() as $oIp ) {
			$aIps[] = $oIp->ip;
		}
		return $aIps;
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	public function isIpOnManualBlackList( $sIp ) {
		return $this->isIpOnList( $sIp, ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK );
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	public function isIpOnWhiteList( $sIp ) {
		return $this->isIpOnList( $sIp, ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE );
	}

	/**
	 * @param string $sIp
	 * @return bool|array - will return the associative array of the single row data
	 */
	public function isIpToBeBlocked( $sIp ) {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		$oIp = $this->getAutoBlackListIp( $sIp );
		return ( $oIp instanceof IPs\EntryVO && $oIp->getTransgressions() >= $oFO->getOptTransgressionLimit() );
	}

	/**
	 * @param string $sIp
	 * @param string $sList
	 * @return bool
	 */
	private function isIpOnList( $sIp, $sList ) {
		$bOnList = false;

		/** @var IPs\Select $oSelect */
		$oSelect = $this->getDbHandler()->getQuerySelector();
		foreach ( $oSelect->allFromList( $sList ) as $oIp ) {
			try {
				if ( $this->loadIpService()->checkIp( $sIp, $oIp->ip ) ) {
					$bOnList = true;
					break;
				}
			}
			catch ( \Exception $oE ) {
			}
		}

		return $bOnList;
	}

	/**
	 * @param string $sIp
	 * @param string $sLabel
	 * @return IPs\EntryVO|null
	 */
	public function addIpToWhiteList( $sIp, $sLabel = '' ) {
		return $this->addIpToManualList( $sIp, ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE, $sLabel );
	}

	/**
	 * @param string $sIp
	 * @param string $sLabel
	 * @return IPs\EntryVO|null
	 */
	public function addIpToBlackList( $sIp, $sLabel = '' ) {
		return $this->addIpToManualList( $sIp, ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK, $sLabel );
	}

	/**
	 * @param string $sIp
	 * @param string $sList
	 * @param string $sLabel
	 * @return IPs\EntryVO|null
	 */
	protected function addIpToManualList( $sIp, $sList, $sLabel = '' ) {
		$sIp = trim( $sIp );

		$oDbh = $this->getDbHandler();

		/** @var IPs\Select $oSelect */
		$oSelect = $oDbh->getQuerySelector();
		/** @var IPs\EntryVO $oIp */
		$oIp = $oSelect->filterByIp( $sIp )
					   ->filterByList( $sList )
					   ->first();

		if ( empty( $oIp ) ) {
			$oIp = $this->addIpToList( $sIp, $sList, $sLabel );
		}
		else if ( $sLabel != $oIp->getLabel() ) {
			/** @var IPs\Update $oUp */
			$oUp = $oDbh->getQueryUpdater();
			$oUp->updateLabel( $oIp, $sLabel );
		}
		return $oIp;
	}

	/**
	 * @param string $sIp
	 */
	private function addIpToAutoBlackList( $sIp ) {
		$oIp = $this->addIpToList( $sIp, ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK, 'auto' );
		/** @var IPs\Update $oUp */
		$oUp = $this->getDbHandler()->getQueryUpdater();
		( $oIp instanceof IPs\EntryVO ) && $oUp->incrementTransgressions( $oIp );
	}

	/**
	 * ADDITION OF ANY IP TO ANY LIST SHOULD GO THROUGH HERE.
	 * @param string $sIp
	 * @param string $sList
	 * @param string $sLabel
	 * @return IPs\EntryVO|null
	 */
	private function addIpToList( $sIp, $sList, $sLabel = '' ) {
		$oIp = null;

		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();

		// Never add a reserved IP to any black list
		if ( $sList == self::LIST_MANUAL_WHITE || !in_array( $sIp, $oFO->getReservedIps() ) ) {
			$oDbh = $this->getDbHandler();

			// delete any previous old entries as we go.
			/** @var IPs\Delete $oDel */
			$oDel = $oDbh->getQueryDeleter();
			$oDel->deleteIpOnList( $sIp, $sList );

			/** @var IPs\EntryVO $oTempIp */
			$oTempIp = $oDbh->getVo();
			$oTempIp->ip = $sIp;
			$oTempIp->list = $sList;
			$oTempIp->label = empty( $sLabel ) ? _wpsf__( 'No Label' ) : trim( $sLabel );

			if ( $oDbh->getQueryInserter()->insert( $oTempIp ) ) {
				/** @var IPs\EntryVO $oIp */
				$oIp = $this->getDbHandler()
							->getQuerySelector()
							->setWheresFromVo( $oTempIp )
							->first();
			}
		}

		return $oIp;
	}

	/**
	 * The auto black list isn't a simple lookup, but rather has an auto expiration
	 * @param string $sIp
	 * @return IPs\EntryVO|null
	 */
	protected function getAutoBlackListIp( $sIp ) {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		/** @var IPs\Select $oSelect */
		$oSelect = $this->getDbHandler()->getQuerySelector();
		/** @var IPs\EntryVO $oIp */
		$oIp = $oSelect->filterByIp( $sIp )
					   ->filterByList( ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK )
					   ->filterByLastAccessAfter( $this->time() - $oFO->getAutoExpireTime() )
					   ->first();
		return $oIp;
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		return "CREATE TABLE %s (
				id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				ip varchar(40) NOT NULL DEFAULT '',
				label varchar(255) NOT NULL DEFAULT '',
				list varchar(4) NOT NULL DEFAULT '',
				ip6 tinyint(1) NOT NULL DEFAULT 0,
				is_range tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
				transgressions tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
				last_access_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id)
			) %s;";
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'ip_list_table_columns' );
		return is_array( $aDef ) ? $aDef : array();
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Handler
	 */
	protected function createDbHandler() {
		return new \FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Handler();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Select
	 */
	public function getQuerySelector() {
		return $this->getDbHandler()->getQuerySelector();
	}

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		return $oFO->getAutoExpireTime();
	}

	/**
	 * We only clean-up expired black list IPs
	 * @return bool
	 */
	public function cleanupDatabase() {
		if ( $this->getDbHandler()->isTable() ) {
			/** @var IPs\Delete $oDel */
			$oDel = $this->getDbHandler()->getQueryDeleter();
			$oDel->filterByLists( [ self::LIST_AUTO_BLACK, self::LIST_MANUAL_BLACK ] )
				 ->filterByLastAccessBefore( $this->time() - $this->getAutoExpirePeriod() )
				 ->query();
		}
		return true;
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Delete
	 */
	public function getQueryDeleter() {
		return parent::getQueryDeleter();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Insert
	 */
	public function getQueryInserter() {
		return parent::getQueryInserter();
	}

	/**
	 * @deprecated
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Update
	 */
	public function getQueryUpdater() {
		return parent::getQueryUpdater();
	}
}