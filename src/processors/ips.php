<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ShieldProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Ips extends ShieldProcessor {

	/**
	 */
	public function run() {
		if ( !$this->isReadyToRun() ) {
			return;
		}

		/** @var IPs\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isEnabledAutoBlackList() ) {

			( new IPs\Components\UnblockIpByFlag() )
				->setMod( $this->getMod() )
				->run();

			$this->processBlacklist();

			$oCon = $this->getCon();
			add_filter( $oCon->prefix( 'firewall_die_message' ), [ $this, 'fAugmentFirewallDieMessage' ] );
			add_action( $oCon->prefix( 'pre_plugin_shutdown' ), [ $this, 'doBlackMarkCurrentVisitor' ] );
			add_action( 'shield_security_offense', [ $this, 'processCustomShieldOffense' ], 10, 3 );
		}
	}

	public function onWpInit() {
		parent::onWpInit();
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();

		if ( $this->isReadyToRun() && $oOpts->isEnabledAutoBlackList()
			 && !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$oMod->isVerifiedBot() ) {
				if ( $oOpts->isEnabledTrackXmlRpc() ) {
					( new IPs\BotTrack\TrackXmlRpc() )
						->setMod( $oMod )
						->run();
				}
				if ( $oOpts->isEnabledTrack404() ) {
					( new IPs\BotTrack\Track404() )
						->setMod( $oMod )
						->run();
				}
				if ( $oOpts->isEnabledTrackLoginFailed() ) {
					( new IPs\BotTrack\TrackLoginFailed() )
						->setMod( $oMod )
						->run();
				}
				if ( $oOpts->isEnabledTrackLoginInvalid() ) {
					( new IPs\BotTrack\TrackLoginInvalid() )
						->setMod( $oMod )
						->run();
				}
				if ( $oOpts->isEnabledTrackFakeWebCrawler() ) {
					( new IPs\BotTrack\TrackFakeWebCrawler() )
						->setMod( $oMod )
						->run();
				}
			}

			/** Always run link cheese regardless of the verified bot or not */
			if ( $oOpts->isEnabledTrackLinkCheese() ) {
				( new IPs\BotTrack\TrackLinkCheese() )
					->setMod( $oMod )
					->run();
			}
		}
	}

	/**
	 * Allows 3rd parties to trigger Shield offenses
	 * @param string $sMessage
	 * @param int    $nOffenseCount
	 * @param bool   $bIncludeLoggedIn
	 */
	public function processCustomShieldOffense( $sMessage, $nOffenseCount = 1, $bIncludeLoggedIn = true ) {
		if ( $this->getCon()->isPremiumActive() ) {
			if ( empty( $sMessage ) ) {
				$sMessage = __( 'No custom message provided.', 'wp-simple-firewall' );
			}

			if ( $bIncludeLoggedIn || !did_action( 'init' ) || !Services::WpUsers()->isUserLoggedIn() ) {
				$this->getCon()
					 ->fireEvent(
						 'custom_offense',
						 [
							 'audit'         => [ 'message' => $sMessage ],
							 'offense_count' => $nOffenseCount
						 ]
					 );
			}
		}
	}

	/**
	 * @param array $aMessages
	 * @return array
	 */
	public function fAugmentFirewallDieMessage( $aMessages ) {
		if ( !is_array( $aMessages ) ) {
			$aMessages = [];
		}
		$nRemaining = ( new IPs\Components\QueryRemainingOffenses() )
			->setMod( $this->getMod() )
			->run( Services::IP()->getRequestIp() );
		$aMessages[] = sprintf( '<p>%s</p>', sprintf(
			$this->getMod()->getTextOpt( 'text_remainingtrans' ),
			$nRemaining
		) );

		return $aMessages;
	}

	private function processBlacklist() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		if ( !$oMod->isVisitorWhitelisted() ) {

			$bIpBlocked = ( new IPs\Components\QueryIpBlock() )
				->setMod( $oMod )
				->setIp( Services::IP()->getRequestIp() )
				->run();

			if ( $bIpBlocked ) {
				$this->setIfLogRequest( false ); // don't log traffic from killed requests
				try {
					if ( $this->processAutoUnblockRequest() ) {
						return;
					}
				}
				catch ( \Exception $oE ) {
				}
				$this->getCon()->fireEvent( 'conn_kill' );
				$this->renderKillPage();
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	private function processAutoUnblockRequest() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();
		$oReq = Services::Request();

		if ( $oOpts->isEnabledAutoUserRecover() && $oReq->isPost()
			 && $oReq->request( 'action' ) == $oMod->prefix() && $oReq->request( 'exec' ) == 'uau' ) {

			if ( check_admin_referer( $oReq->request( 'exec' ), 'exec_nonce' ) !== 1 ) {
				throw new \Exception( 'Nonce failed' );
			}
			if ( strlen( $oReq->post( 'icwp_wpsf_login_email' ) ) > 0 ) {
				throw new \Exception( 'Email should not be provided in honeypot' );
			}

			$sIp = Services::IP()->getRequestIp();
			if ( $oReq->post( 'ip' ) != $sIp ) {
				throw new \Exception( 'IP does not match' );
			}

			$oLoginMod = $this->getCon()->getModule_LoginGuard();
			$sGasp = $oReq->post( $oLoginMod->getGaspKey() );
			if ( empty( $sGasp ) ) {
				throw new \Exception( 'GASP failed' );
			}

			if ( !$oOpts->getCanIpRequestAutoUnblock( $sIp ) ) {
				throw new \Exception( 'IP already processed in the last 24hrs' );
			}
			$oMod->updateIpRequestAutoUnblockTs( $sIp );

			( new IPs\Lib\Ops\DeleteIp() )
				->setDbHandler( $oMod->getDbHandler_IPs() )
				->setIP( $sIp )
				->fromBlacklist();
			Services::Response()->redirectToHome();
		}

		return false;
	}

	private function renderKillPage() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();
		$oCon = $this->getCon();
		$oLoginMod = $oCon->getModule_LoginGuard();

		$sUniqId = 'uau'.uniqid();

		$sIp = Services::IP()->getRequestIp();
		$nTimeRemaining = max( floor( $oOpts->getAutoExpireTime()/60 ), 0 );
		$aData = [
			'strings' => [
				'title'   => sprintf( __( "You've been blocked by the %s plugin", 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$oCon->getPluginSpec()[ 'meta' ][ 'url_repo_home' ],
						$oCon->getHumanName()
					)
				),
				'lines'   => [
					sprintf( __( 'Time remaining on black list: %s', 'wp-simple-firewall' ),
						sprintf( _n( '%s minute', '%s minutes', $nTimeRemaining, 'wp-simple-firewall' ), $nTimeRemaining )
					),
					sprintf( __( 'You tripped the security plugin defenses a total of %s times making you a suspect.', 'wp-simple-firewall' ), $oOpts->getOffenseLimit() ),
					sprintf( __( 'If you believe this to be in error, please contact the site owner and quote your IP address below.', 'wp-simple-firewall' ) ),
				],
				'your_ip' => 'Your IP address',
				'unblock' => [
					'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
					'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
					'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
				],
			],
			'vars'    => [
				'nonce'        => $oMod->getNonceActionData( 'uau' ),
				'ip'           => $sIp,
				'gasp_element' => $oMod->renderTemplate(
					'snippets/gasp_js.php',
					[
						'sCbName'   => $oLoginMod->getGaspKey(),
						'sLabel'    => $oLoginMod->getTextImAHuman(),
						'sAlert'    => $oLoginMod->getTextPleaseCheckBox(),
						'sMustJs'   => __( 'You MUST enable Javascript to be able to login', 'wp-simple-firewall' ),
						'sUniqId'   => $sUniqId,
						'sUniqElem' => 'icwp_wpsf_login_p'.$sUniqId,
						'strings'   => [
							'loading' => __( 'Loading', 'wp-simple-firewall' )
						]
					]
				),
			],
			'flags'   => [
				'is_autorecover'   => $oOpts->isEnabledAutoUserRecover(),
				'is_uau_permitted' => $oOpts->getCanIpRequestAutoUnblock( $sIp ),
			],
		];
		Services::WpGeneral()
				->wpDie(
					$oMod->renderTemplate( '/snippets/blacklist_die.twig', $aData, true )
				);
	}

	/**
	 */
	public function doBlackMarkCurrentVisitor() {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();

		if ( $oOpts->isEnabledAutoBlackList() && !$this->getCon()->isPluginDeleting()
			 && $oMod->getIfIpTransgressed() && !$oMod->isVerifiedBot() && !$oMod->isVisitorWhitelisted() ) {
			$this->processTransgression();
		}
	}

	/**
	 */
	private function processTransgression() {
		/** @var ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oCon = $this->getCon();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();

		$oBlackIp = ( new IPs\Lib\Ops\AddIp() )
			->setMod( $oMod )
			->setIP( Services::IP()->getRequestIp() )
			->toAutoBlacklist();

		if ( $oBlackIp instanceof Databases\IPs\EntryVO ) {
			$nLimit = $oOpts->getOffenseLimit();
			$nCurrentOffenses = $oBlackIp->transgressions;

			$mAction = $oMod->getIpOffenceCount();
			$bToBlock = ( $oBlackIp->blocked_at == 0 ) && ( $nCurrentOffenses < $nLimit )
						&& ( $mAction == PHP_INT_MAX ) || ( $nLimit - $nCurrentOffenses == 1 );

			$nNewOffensesTotal = $oBlackIp->transgressions +
								 min( 1, $bToBlock ? $nLimit - $nCurrentOffenses : $mAction );

			/** @var Databases\IPs\Update $oUp */
			$oUp = $oMod->getDbHandler_IPs()->getQueryUpdater();
			$oUp->updateTransgressions( $oBlackIp, $nNewOffensesTotal );

			$oCon->fireEvent( $bToBlock ? 'ip_blocked' : 'ip_offense',
				[
					'audit' => [
						'from' => $nCurrentOffenses,
						'to'   => $nNewOffensesTotal,
					]
				]
			);

			/**
			 * When we block, we also want to increment offense stat, but we don't
			 * want to also audit the offense (only audit the block),
			 * so we fire ip_offense but suppress the audit
			 */
			if ( $bToBlock ) {
				$oUp = $oMod->getDbHandler_IPs()->getQueryUpdater();
				$oUp->setBlocked( $oBlackIp );
				$oCon->fireEvent( 'ip_offense', [ 'suppress_audit' => true ] );
			}
		}
	}

	/**
	 * @return string
	 * @deprecated 8.5
	 */
	public function getRemainingTransgressions() {
		return 0;
	}

	/**
	 * @param string $sIp
	 * @return int
	 * @deprecated 8.5
	 */
	private function getTransgressions( $sIp ) {
		return 0;
	}

	/**
	 * @return string
	 * @deprecated 8.5
	 */
	private function getTextOfRemainingTransgressions() {
		return '';
	}

	/**
	 * @return Databases\IPs\EntryVO[]
	 * @deprecated 8.5
	 */
	public function getWhitelistIpsData() {
		return [];
	}

	/**
	 * @return string[]
	 * @deprecated 8.5
	 */
	public function getWhitelistIps() {
		return [];
	}

	/**
	 * @param string $sIp
	 * @param string $sLabel
	 * @return Databases\IPs\EntryVO|null
	 * @deprecated 8.5
	 */
	public function addIpToWhiteList( $sIp, $sLabel = '' ) {
		return ( new IPs\Lib\Ops\AddIp() )
			->setMod( $this->getMod() )
			->setIP( $sIp )
			->toManualWhitelist( $sLabel );
	}

	/**
	 * @param string $sIp
	 * @param string $sList
	 * @param string $sLabel
	 * @return Databases\IPs\EntryVO|null
	 * @deprecated 8.5
	 */
	private function addIpToManualList( $sIp, $sList, $sLabel = '' ) {
		return null;
	}

	/**
	 * ADDITION OF ANY IP TO ANY LIST SHOULD GO THROUGH HERE.
	 * @param string $sIp
	 * @param string $sList
	 * @param string $sLabel
	 * @return Databases\IPs\EntryVO|null
	 * @deprecated 8.5
	 */
	private function addIpToList( $sIp, $sList, $sLabel = '' ) {
		return null;
	}

	/**
	 * The auto black list isn't a simple lookup, but rather has an auto expiration
	 * @param string $sIp
	 * @return Databases\IPs\EntryVO|null
	 * @deprecated 8.5
	 */
	protected function getBlackListIp( $sIp ) {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();
		/** @var Databases\IPs\Select $oSelect */
		$oSelect = $oMod->getDbHandler_IPs()->getQuerySelector();
		/** @var Databases\IPs\EntryVO $oIp */
		$oIp = $oSelect->filterByIp( $sIp )
					   ->filterByBlacklist()
					   ->filterByLastAccessAfter( Services::Request()->ts() - $oOpts->getAutoExpireTime() )
					   ->first();
		return $oIp;
	}

	/**
	 * The auto black list isn't a simple lookup, but rather has an auto expiration
	 * @param string $sIp
	 * @return Databases\IPs\EntryVO|null
	 * @deprecated 8.5
	 */
	protected function getAutoBlackListIp( $sIp ) {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();
		/** @var Databases\IPs\Select $oSelect */
		$oSelect = $oMod->getDbHandler_IPs()->getQuerySelector();
		return $oSelect->filterByIp( $sIp )
					   ->filterByList( $oMod::LIST_AUTO_BLACK )
					   ->filterByLastAccessAfter( Services::Request()->ts() - $oOpts->getAutoExpireTime() )
					   ->first();
	}

	/**
	 * @deprecated 8.5
	 */
	private function processAutoUnblockByFlag() {
	}
}