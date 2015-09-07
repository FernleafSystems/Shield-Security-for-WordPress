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

			add_action( 'in_admin_footer', array( $this, 'printVisitorIpFooter' ) );

			if ( $this->getController()->getIsValidAdminArea() ) {
				$this->maintainPluginLoadPosition();
			}
		}

		/**
		 * Sets this plugin to be the first loaded of all the plugins.
		 */
		protected function maintainPluginLoadPosition() {
			$oWp = $this->loadWpFunctionsProcessor();
			$sBaseFile = $this->getController()->getPluginBaseFile();
			$nLoadPosition = $oWp->getActivePluginLoadPosition( $sBaseFile );
			if ( $nLoadPosition !== 0 && $nLoadPosition > 0 ) {
				$oWp->setActivePluginLoadFirst( $sBaseFile );
			}
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
			$oCon = $this->getController();

			if ( $oCon->getIsValidAdminArea() ) {
				// always show this notice
				$this->adminNoticeForceOffActive();
				$this->adminNoticePhpMinimumVersion53();
				if ( $this->getIfShowAdminNotices() ) {
					$this->adminNoticeMailingListSignup();
					$this->adminNoticeTranslations();
					$this->adminNoticePluginUpgradeAvailable();
					$this->adminNoticePostPluginUpgrade();
				}
				if ( $oCon->getIsPage_PluginAdmin() ) {
					$this->adminNoticeYouAreWhitelisted();
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

		public function printVisitorIpFooter() {
			echo sprintf( '<p><span>%s</span></p>', sprintf( _wpsf__( 'Your IP address is: %s' ), $this->human_ip() ) );
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
		 */
		public function adminNoticeYouAreWhitelisted() {

			if ( apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'visitor_is_whitelisted' ), false ) ) {

				$aDisplayData = array(
					'render-slug' => 'visitor-whitelisted',
					'strings' => array(
						'your_ip' => sprintf( _wpsf__( 'Your IP address is: %s' ), $this->loadDataProcessor()->getVisitorIpAddress() ),
						'notice_message' => sprintf(
							_wpsf__( 'Notice - %s' ),
							_wpsf__( 'You should know that your IP address is whitelisted and features you activate do not apply to you.' )
						),
					)
				);
				$this->insertAdminNotice( $aDisplayData );
			}
		}

		/**
		 * @return array
		 */
		public function adminNoticeForceOffActive() {

			if ( $this->getFeatureOptions()->getIfOverrideOff() ) {
				$aDisplayData = array(
					'render-slug' => 'override-forceoff',
					'strings' => array(
						'message' => sprintf( _wpsf__('Warning - %s.'), sprintf( _wpsf__('%s is not currently running' ), $this->getController()->getHumanName() ) ),
						'force_off' => sprintf( _wpsf__( 'Please delete the "%s" file to reactivate the Firewall processing' ), 'forceOff' )
					)
				);
				$this->insertAdminNotice( $aDisplayData );
			}
		}

		/**
		 */
		public function adminNoticePhpMinimumVersion53() {

			$oDp = $this->loadDataProcessor();
			if ( $oDp->getPhpVersionIsAtLeast( '5.3.2' ) ) {
				return;
			}

			$oCon = $this->getController();
			$oWp = $this->loadWpFunctionsProcessor();
			$sCurrentMetaValue = $this->loadWpUsersProcessor()->getUserMeta( $oCon->doPluginOptionPrefix( 'php53_version_warning' ) );
			if ( empty( $sCurrentMetaValue ) || $sCurrentMetaValue === 'Y' ) {
				return;
			}

			$aDisplayData = array(
				'render-slug' => 'minimum-php53',
				'strings' => array(
					'your_php_version' => sprintf( _wpsf__( 'Your PHP version is very old: %s' ), $oDp->getPhpVersion() ),
					'future_versions_not_supported' => sprintf( _wpsf__( 'Future versions of the %s plugin will not support your PHP version.' ), $oCon->getHumanName() ),
					'ask_host_to_upgrade' => sprintf( _wpsf__( 'You should ask your host to upgrade or provide a much newer PHP version.' ), $oCon->getHumanName() ),
					'any_questions' => sprintf( _wpsf__( 'If you have any questions, please leave us a message in the forums.' ), $oCon->getHumanName() ),
					'dismiss' => _wpsf__( 'Dismiss this notice' ),
					'forums' => __('Support Forums'),
				),
				'hrefs' => array(
					'form_action' => $oCon->getPluginUrl_AdminMainPage().'&'.$oCon->doPluginPrefix( 'hide_php53_warning' ).'=1',
					'forums' => 'https://wordpress.org/support/plugin/wp-simple-firewall',
					'redirect' => $oWp->getUrl_CurrentAdminPage(),
				)
			);
			$this->insertAdminNotice( $aDisplayData );
		}

		/**
		 */
		public function adminNoticeMailingListSignup() {
			$oFO = $this->getFeatureOptions();

			$sCurrentMetaValue = $this->loadWpUsersProcessor()->getUserMeta( $oFO->prefixOptionKey( 'plugin_mailing_list_signup' ) );
			if ( $sCurrentMetaValue == 'Y' ) {
				return;
			}

			$nDays = $this->getInstallationDays();
			if ( $nDays >= 5 ) {
				$aDisplayData = array(
					'render-slug' => 'security-group-signup',
					'strings' => array(
						'yes' => "Yes please! I'd love to join in and learn more",
						'no' => "No thanks, I'm not interested in such groups",
						'we_dont_spam' => "(don't worry, we don't SPAM. Ever.)",
						'your_name' => _wpsf__( 'Your Name' ),
						'your_email' => _wpsf__( 'Your Email' ),
						'summary' => 'The WordPress Simple Firewall team is running an initiative (with currently 1000+ members) to raise awareness of WordPress Security
				and to provide further help with the WordPress Simple Firewall plugin. Get Involved here:',
					),
					'hrefs' => array(
						'form_action' => '//hostliketoast.us2.list-manage.com/subscribe/post?u=e736870223389e44fb8915c9a&id=0e1d527259',
						'hide_notice' => $this->getController()->getPluginUrl_AdminMainPage().'&'.$oFO->doPluginPrefix( 'hide_mailing_list_signup' ).'=1'
					),
					'install_days' => $nDays
				);
				$this->insertAdminNotice( $aDisplayData );
			}
		}

		/**
		 */
		public function adminNoticePluginUpgradeAvailable() {

			$sBaseFile = $this->getController()->getPluginBaseFile();
			$oWp = $this->loadWpFunctionsProcessor();

			if ( !$oWp->getIsPage_Updates() && $oWp->getIsPluginUpdateAvailable( $sBaseFile ) ) { // Don't show on the update page

				$aDisplayData = array(
					'render-slug' => 'plugin-update-available',
					'strings' => array(
						'plugin_update_available' => sprintf( _wpsf__( 'There is an update available for the "%s" plugin.' ), $this->getController()->getHumanName() ),
						'click_update' => _wpsf__( 'Please click to update immediately' )
					),
					'hrefs' => array(
						'upgrade_link' =>  $oWp->getPluginUpgradeLink( $sBaseFile )
					)
				);
				$this->insertAdminNotice( $aDisplayData );
			}
		}

		/**
		 */
		public function adminNoticePostPluginUpgrade() {
			$oFO = $this->getFeatureOptions();
			$oController = $this->getController();

			$sCurrentMetaValue = $this->loadWpUsersProcessor()->getUserMeta( $oController->doPluginOptionPrefix( 'current_version' ) );
			if ( empty( $sCurrentMetaValue ) || $sCurrentMetaValue === $oFO->getVersion() ) {
				return;
			}
			$this->updateVersionUserMeta(); // we show the upgrade notice only once.

			if ( $this->getInstallationDays() <= 1 ) {
				$sMessage = sprintf(
					_wpsf__( "Notice - %s" ),
					sprintf( _wpsf__( "The %s plugin does not automatically turn on certain features when you install." ), $oController->getHumanName() )
				);
			}
			else {
				$sMessage = sprintf(
					_wpsf__( "Notice - %s" ),
					sprintf( _wpsf__( "The %s plugin has been recently upgraded, but please remember that new features may not be automatically enabled." ), $oController->getHumanName() )
				);
			}

			$aDisplayData = array(
				'render-slug' => 'plugin-updated',
				'strings' => array(
					'main_message' => $sMessage,
					'read_homepage' => _wpsf__( 'Click to read about any important updates from the plugin home page.' ),
					'link_title' => $oController->getHumanName(),
				),
				'hrefs' => array(
					'read_homepage' => 'http://icwp.io/27',
					'hide_notice' => $oController->getPluginUrl_AdminMainPage().'&'.$oFO->doPluginPrefix( 'hide_update_notice' ).'=1'
				),
			);
			$this->insertAdminNotice( $aDisplayData );
		}

		/**
		 * Updates the current (or supplied user ID) user meta data with the version of the plugin
		 * @param integer $nId
		 */
		protected function updateVersionUserMeta( $nId = null ) {
			$oCon = $this->getController();
			$oCon->loadWpUsersProcessor()->updateUserMeta( $oCon->doPluginOptionPrefix( 'current_version' ), $oCon->getVersion(), $nId );
		}

		/**
		 */
		public function adminNoticeTranslations() {

			$oController = $this->getController();
			$oWp = $this->loadWpFunctionsProcessor();
			$sCurrentMetaValue = $this->loadWpUsersProcessor()->getUserMeta( $oController->doPluginOptionPrefix( 'plugin_translation_notice' ) );
			if ( empty( $sCurrentMetaValue ) || $sCurrentMetaValue === 'Y' || $this->getInstallationDays() < 40 ) {
				return;
			}

			$aDisplayData = array(
				'render-slug' => 'translate-plugin',
				'strings' => array(
					'like_to_help' => sprintf( _wpsf__( "Would you like to help translate the %s plugin into your language?" ), $oController->getHumanName() ),
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
			$this->insertAdminNotice( $aDisplayData );
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