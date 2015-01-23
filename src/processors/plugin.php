<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

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

			$oCon = $this->getController();
			if ( $oCon->getIsValidAdminArea() ) {

				// always show this notice
				add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeForceOffActive' ) );
				add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeFeedback' ) );
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
			$oFO = $this->getFeatureOptions();
			$bWhitelisted = $this->fGetIsVisitorWhitelisted( false );
			if ( $bWhitelisted ) {
				$sIpAddress = $this->loadDataProcessor()->getVisitorIpAddress();
				ob_start();
				include( $oFO->getViewSnippet( 'admin_notice_vistor_whitelisted' ) );
				$sNoticeMessage = ob_get_contents();
				ob_end_clean();
				$aAdminNotices[] = $this->getAdminNoticeHtml( $sNoticeMessage, 'updated', false );
			}
			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticeForceOffActive( $aAdminNotices ) {
			$oFO = $this->getFeatureOptions();
			$fOverride = $oFO->getIfOverrideOff();
			if ( $fOverride ) {
				ob_start();
				include( $oFO->getViewSnippet( 'admin_notice_override' ) );
				$sNoticeMessage = ob_get_contents();
				ob_end_clean();
				$aAdminNotices[] = $this->getAdminNoticeHtml( $sNoticeMessage, 'error', false );
			}
			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticeFeedback( $aAdminNotices ) {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();

			$aAdminFeedbackNotice = $this->getOption( 'feedback_admin_notice' );

			if ( !empty( $aAdminFeedbackNotice ) && is_array( $aAdminFeedbackNotice ) ) {

				foreach ( $aAdminFeedbackNotice as $sNotice ) {
					if ( empty( $sNotice ) || !is_string( $sNotice ) ) {
						continue;
					}
					$aAdminNotices[] = $this->getAdminNoticeHtml( '<p>'.$sNotice.'</p>', 'updated', false );
				}
				$oFO->doClearAdminFeedback( 'feedback_admin_notice', array() );
			}

			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticeMailingListSignup( $aAdminNotices ) {
			$oFO = $this->getFeatureOptions();

			$nDays = $this->getInstallationDays();
			if ( $nDays < 2 ) {
				return $aAdminNotices;
			}

			$sCurrentMetaValue = $this->loadWpFunctionsProcessor()->getUserMeta( $oFO->prefixOptionKey( 'plugin_mailing_list_signup' ) );
			if ( $sCurrentMetaValue == 'Y' ) {
				return $aAdminNotices;
			}

			$sLink_HideNotice = $this->getController()->getPluginUrl_AdminMainPage().'&'.$oFO->doPluginPrefix( 'hide_mailing_list_signup' ).'=1';
			ob_start();
			include( $oFO->getViewSnippet( 'admin_notice_mailchimp' ) );
			$sNoticeMessage = ob_get_contents();
			ob_end_clean();

			$aAdminNotices[] = $this->getAdminNoticeHtml( $sNoticeMessage, 'updated', false );
			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticePluginUpgradeAvailable( $aAdminNotices ) {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();

			$oWp = $this->loadWpFunctionsProcessor();
			// Don't show on the update page
			if ( $oWp->getIsPage_Updates() || !$oWp->getIsPluginUpdateAvailable( $oFO->getPluginBaseFile() ) ) {
				return $aAdminNotices;
			}

			$sNoticeMessage = '<p>'.sprintf( _wpsf__( 'There is an update available for your WordPress Security plugin: %s.' ), '<strong>'.$this->getController()->getHumanName().'</strong>' ).'</p>';
			$sNoticeMessage .= sprintf( '<a href="%s" class="button">'._wpsf__( 'Please click to update immediately' ).'</a>', $oWp->getPluginUpgradeLink( $oFO->getPluginBaseFile() ) );

			$aAdminNotices[] = $this->getAdminNoticeHtml( $sNoticeMessage, 'updated', false );
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

			if ( $this->getInstallationDays() <= 1 ) {
				$sMessage = sprintf(
					_wpsf__( "Note: The %s plugin does not automatically turn on features when you install." ),
					$oController->getHumanName()
				);
			}
			else {
				$sMessage = sprintf(
					_wpsf__( "Note: The %s plugin has been recently upgraded, but please remember that any new features are not automatically enabled." ),
					$oController->getHumanName()
				);
			}
			$sMessage .= '<br />'.sprintf(
					'<a href="%s" id="fromIcwp" title="%s" target="_blank">%s</a>',
					'http://icwp.io/27',
					$oController->getHumanName(),
					_wpsf__( 'Click to read about any important updates from the plugin home page.' )
				);
			$sButtonText = _wpsf__( 'Okay, hide this notice and go to the plugin dashboard.' );

			$sMetaFlag = $oController->doPluginPrefix( 'hide_update_notice' );
			$sAction = $oController->getPluginUrl_AdminMainPage().'&'.$sMetaFlag.'=1';
			$sRedirectPage = $oWp->getUrl_CurrentAdminPage();
			ob_start();
			include( $oFO->getViewSnippet( 'admin_notice_plugin_upgraded' ) );
			$sNoticeMessage = ob_get_contents();
			ob_end_clean();

			$aAdminNotices[] = $this->getAdminNoticeHtml( $sNoticeMessage, 'updated', false );
			return $aAdminNotices;
		}

		/**
		 * @param array $aAdminNotices
		 * @return array
		 */
		public function adminNoticeTranslations( $aAdminNotices ) {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();

			if ( $this->getInstallationDays() < 7 ) {
				return $aAdminNotices;
			}

			$oController = $this->getController();

			$oWp = $this->loadWpFunctionsProcessor();
			$sCurrentMetaValue = $oWp->getUserMeta( $oController->doPluginOptionPrefix( 'plugin_translation_notice' ) );
			if ( empty( $sCurrentMetaValue ) || $sCurrentMetaValue === 'Y' ) {
				return $aAdminNotices;
			}

			ob_start();
			$sMetaFlag = $oController->doPluginPrefix( 'hide_translation_notice' );
			$sAction = $oController->getPluginUrl_AdminMainPage().'&'.$sMetaFlag.'=1';
			$sRedirectPage = $oWp->getUrl_CurrentAdminPage();
			include( $oFO->getViewSnippet( 'admin_notice_translate_plugin' ) );
			$sNoticeMessage = ob_get_contents();
			ob_end_clean();

			$aAdminNotices[] = $this->getAdminNoticeHtml( $sNoticeMessage, 'updated', false );
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