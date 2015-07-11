<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Plugin', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_Plugin extends ICWP_WPSF_Processor_Base {

		/**
		 * @var bool
		 */
		protected $bVisitorIsWhitelisted;

		/**
		 */
		public function run() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();

			$this->removePluginConflicts();
			add_filter( $oFO->doPluginPrefix( 'show_marketing' ), array( $this, 'getIsShowMarketing' ) );
			add_filter( $oFO->doPluginPrefix( 'delete_on_deactivate' ), array( $this, 'getIsDeleteOnDeactivate' ) );
			add_filter( $oFO->doPluginPrefix( 'visitor_is_whitelisted' ), array( $this, 'fGetIsVisitorWhitelisted' ) );

			if ( $this->getIsOption( 'display_plugin_badge', 'Y' ) ) {
				add_action( 'wp_footer', array( $this, 'printPluginBadge' ) );
			}

			add_action( 'widgets_init', array( $this, 'addPluginBadgeWidget' ) );
		}

		public function addPluginBadgeWidget() {
			$this->loadWpWidgets();
			require_once( dirname(__FILE__).ICWP_DS.'plugin_badgewidget.php' );
			ICWP_WPSF_Processor_Plugin_BadgeWidget::SetFeatureOptions( $this->getFeatureOptions() );
			register_widget( 'ICWP_WPSF_Processor_Plugin_BadgeWidget' );
		}

		/**
		 */
		public function addToAdminNotices() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();

			if ( $oFO->getController()->getIsValidAdminArea() ) {
				$oCon = $this->getController();
				// always show this notice
				add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeForceOffActive' ) );
				if ( $this->getIfShowAdminNotices() ) {
					add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeMailingListSignup' ) );
					add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeTranslations' ) );
					add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticePluginUpgradeAvailable' ) );
					add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticePostPluginUpgrade' ) );
				}
				if ( $oCon->getIsPage_PluginAdmin() ) {
					add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeYouAreWhitelisted' ) );
				}
			}
		}

		public function printPluginBadge() {
			$oCon = $this->getController();
			$oRender = $this->loadRenderer( $oCon->getPath_Templates().'html' );
			$sContents = $oRender
				->clearRenderVars()
				->setTemplate( 'plugin_badge' )
				->setTemplateEngineHtml()
				->render();
			echo sprintf( $sContents, $oCon->getPluginUrl_Image( 'pluginlogo_32x32.png' ), $oCon->getHumanName(), $oCon->getHumanName() );
		}

		/**
		 * @param $bIsWhitelisted
		 * @return boolean
		 */
		public function fGetIsVisitorWhitelisted( $bIsWhitelisted ) {
			if ( !isset( $this->bVisitorIsWhitelisted ) ) {
				/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
				$oFO = $this->getFeatureOptions();
				$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
				$aIpWhitelist = $oFO->getIpWhitelistOption();
				$this->bVisitorIsWhitelisted = ( is_array( $aIpWhitelist ) && ( in_array( $sIp, $aIpWhitelist ) ) );
			}
			return $this->bVisitorIsWhitelisted;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticeYouAreWhitelisted( $aAdminNotices ) {

			if ( $this->fGetIsVisitorWhitelisted( false ) ) {

				$aDisplayData = array(
					'strings' => array(
						'your_ip' => sprintf( _wpsf__( 'Your IP address is: %s' ), $this->loadDataProcessor()->getVisitorIpAddress() ),
						'notice_message' => sprintf(
							_wpsf__( 'Notice - %s' ),
							_wpsf__( 'You should know that your IP address is whitelisted and features you activate do not apply to you.' )
						),
					)
				);
				$aAdminNotices[] = $this->getFeatureOptions()->renderAdminNotice( 'visitor-whitelisted', $aDisplayData );
			}
			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticeForceOffActive( $aAdminNotices ) {
			$oFO = $this->getFeatureOptions();

			if ( $oFO->getIfOverrideOff() ) {
				$aDisplayData = array(
					'strings' => array(
						'message' => sprintf( _wpsf__('Warning - %s.'), sprintf( _wpsf__('%s is not currently running' ), $this->getController()->getHumanName() ) ),
						'force_off' => sprintf( _wpsf__( 'Please delete the "%s" file to reactivate the Firewall processing' ), 'forceOff' )
					)
				);
				$aAdminNotices[] = $this->getFeatureOptions()->renderAdminNotice( 'override-forceoff', $aDisplayData );
			}
			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticeMailingListSignup( $aAdminNotices ) {
			$oFO = $this->getFeatureOptions();

			$sCurrentMetaValue = $this->loadWpFunctionsProcessor()->getUserMeta( $oFO->prefixOptionKey( 'plugin_mailing_list_signup' ) );
			if ( $sCurrentMetaValue == 'Y' ) {
				return $aAdminNotices;
			}

			$nDays = $this->getInstallationDays();
			if ( $nDays < 5 ) {
				return $aAdminNotices;
			}

			$aDisplayData = array(
				'strings' => array(
					'yes' => "Yes please! I'd love to join in and learn more",
					'no' => "No thanks, I'm not interested in such groups",
					'we_dont_spam' => "(don't worry, we don't SPAM. Ever.)",
					'your_name' => _wpsf__( 'Your Name' ),
					'your_email' => _wpsf__( 'Your Email' ),
					'summary' => 'The WordPress Simple Firewall team is running an initiative (with currently over 1000 members) to raise awareness of WordPress Security
				and to provide further help with the WordPress Simple Firewall plugin. Get Involved here:',
				),
				'hrefs' => array(
					'form_action' => '//hostliketoast.us2.list-manage.com/subscribe/post?u=e736870223389e44fb8915c9a&id=0e1d527259',
					'hide_notice' => $this->getController()->getPluginUrl_AdminMainPage().'&'.$oFO->doPluginPrefix( 'hide_mailing_list_signup' ).'=1'
				),
				'install_days' => $nDays
			);

			$aAdminNotices[] = $this->getFeatureOptions()->renderAdminNotice( 'security-group-signup', $aDisplayData );
			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticePluginUpgradeAvailable( $aAdminNotices ) {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			$sBaseFile = $oFO->getController()->getPluginBaseFile();

			$oWp = $this->loadWpFunctionsProcessor();
			// Don't show on the update page
			if ( $oWp->getIsPage_Updates() || !$oWp->getIsPluginUpdateAvailable( $sBaseFile ) ) {
				return $aAdminNotices;
			}

			$aDisplayData = array(
				'strings' => array(
					'plugin_update_available' => sprintf( _wpsf__( 'There is an update available for the "%s" plugin.' ), $this->getController()->getHumanName() ),
					'click_update' => _wpsf__( 'Please click to update immediately' )
				),
				'hrefs' => array(
					'upgrade_link' =>  $oWp->getPluginUpgradeLink( $sBaseFile )
				)
			);

			$aAdminNotices[] = $this->getFeatureOptions()->renderAdminNotice( 'plugin_update_available', $aDisplayData );
			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticePostPluginUpgrade( $aAdminNotices ) {
			$oFO = $this->getFeatureOptions();
			$oController = $this->getController();
			$oWp = $this->loadWpFunctionsProcessor();

			$sCurrentMetaValue = $oWp->getUserMeta( $oController->doPluginOptionPrefix( 'current_version' ) );
			if ( empty( $sCurrentMetaValue ) || $sCurrentMetaValue === $oFO->getVersion() ) {
				return $aAdminNotices;
			}
			$this->updateVersionUserMeta(); // we show the upgrade notice only once.

			if ( $this->getInstallationDays() <= 1 ) {
				$sMessage = sprintf(
					sprintf( _wpsf__( "Notice - %s" ), "The %s plugin does not automatically turn on features when you install." ),
					$oController->getHumanName()
				);
			}
			else {
				$sMessage = sprintf(
					sprintf( _wpsf__( "Notice - %s" ), "The %s plugin has been recently upgraded, but please remember that any new features are not automatically enabled." ),
					$oController->getHumanName()
				);
			}

			$aDisplayData = array(
				'strings' => array(
					'main_message' => $sMessage,
					'read_homepage' => _wpsf__( 'Click to read about any important updates from the plugin home page.' ),
					'link_title' => $oController->getHumanName(),
				),
				'hrefs' => array(
					'read_homepage' => 'http://icwp.io/27',
					'hide_notice' => $this->getController()->getPluginUrl_AdminMainPage().'&'.$oFO->doPluginPrefix( 'hide_update_notice' ).'=1'
				),
			);
			$aAdminNotices[] = $this->getFeatureOptions()->renderAdminNotice( 'plugin_updated', $aDisplayData );
			return $aAdminNotices;
		}

		/**
		 * Updates the current (or supplied user ID) user meta data with the version of the plugin
		 *
		 * @param integer $nId
		 */
		protected function updateVersionUserMeta( $nId = null ) {
			$oCon = $this->getController();
			$oCon->loadWpFunctionsProcessor()->updateUserMeta( $oCon->doPluginOptionPrefix( 'current_version' ), $oCon->getVersion(), $nId );
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticeTranslations( $aAdminNotices ) {

			$oController = $this->getController();
			$oWp = $this->loadWpFunctionsProcessor();
			$sCurrentMetaValue = $oWp->getUserMeta( $oController->doPluginOptionPrefix( 'plugin_translation_notice' ) );
			if ( empty( $sCurrentMetaValue ) || $sCurrentMetaValue === 'Y' ) {
				return $aAdminNotices;
			}

			if ( $this->getInstallationDays() < 7 ) {
				return $aAdminNotices;
			}

			$aDisplayData = array(
				'strings' => array(
					'like_to_help' => "Would you like to help translate the WordPress Simple Firewall into your language?",
					'head_over_to' => sprintf( _wpsf__( 'Head over to: %s' ), '' ),
					'site_url' => 'translate.icontrolwp.com',
					'dismiss' => _wpsf__( 'Dismiss this notice' )
				),
				'hrefs' => array(
					'form_action' => $oController->getPluginUrl_AdminMainPage().'&'.$oController->doPluginPrefix( 'hide_translation_notice' ).'=1',
					'redirect' => $oWp->getUrl_CurrentAdminPage(),
					'translate' => 'http://translate.icontrolwp.com'
				)
			);
			$aAdminNotices[] = $this->getFeatureOptions()->renderAdminNotice( 'translate-plugin', $aDisplayData );
			return $aAdminNotices;
		}

		/**
		 * @return bool
		 */
		public function getIsDeleteOnDeactivate() {
			return $this->getFeatureOptions()->getOptIs( 'delete_on_deactivate', 'Y' );
		}

		/**
		 * @param $fShow
		 * @return bool
		 */
		public function getIsShowMarketing( $fShow ) {
			if ( !$fShow ) {
				return $fShow;
			}

			if ( $this->getInstallationDays() < 1 ) {
				$fShow = false;
			}

			$oWpFunctions = $this->loadWpFunctionsProcessor();
			if ( class_exists( 'Worpit_Plugin' ) ) {
				if ( method_exists( 'Worpit_Plugin', 'IsLinked' ) ) {
					$fShow = !Worpit_Plugin::IsLinked();
				}
				else if ( $oWpFunctions->getOption( Worpit_Plugin::$VariablePrefix.'assigned' ) == 'Y'
				          && $oWpFunctions->getOption( Worpit_Plugin::$VariablePrefix.'assigned_to' ) != '' ) {

					$fShow = false;
				}
			}

			return $fShow;
		}

		/**
		 * @return int
		 */
		protected function getInstallationDays() {
			$nTimeInstalled = $this->getFeatureOptions()->getOpt( 'installation_time' );
			if ( empty( $nTimeInstalled ) ) {
				return 0;
			}
			return round( ( time() - $nTimeInstalled ) / DAY_IN_SECONDS );
		}

		/**
		 * Lets you remove certain plugin conflicts that might interfere with this plugin
		 */
		protected function removePluginConflicts() {
			if ( class_exists('AIO_WP_Security') && isset( $GLOBALS['aio_wp_security'] ) ) {
				remove_action( 'init', array( $GLOBALS['aio_wp_security'], 'wp_security_plugin_init'), 0 );
			}
		}

		/**
		 * @return bool
		 */
		protected function getIfShowAdminNotices() {
			return $this->getFeatureOptions()->getOptIs( 'enable_upgrade_admin_notice', 'Y' );
		}
	}

endif;