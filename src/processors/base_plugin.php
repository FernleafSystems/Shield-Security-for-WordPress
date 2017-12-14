<?php

if ( !class_exists( 'ICWP_WPSF_Processor_BasePlugin', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_Processor_BasePlugin extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 */
		public function init() {
			parent::init();
			$oFO = $this->getFeature();
			add_filter( $oFO->prefix( 'show_marketing' ), array( $this, 'getIsShowMarketing' ) );
			add_filter( $oFO->prefix( 'delete_on_deactivate' ), array( $this, 'getIsDeleteOnDeactivate' ) );
		}

		/**
		 */
		public function run() {}

		/**
		 * Override the original collection to then add plugin statistics to the mix
		 * @param $aData
		 * @return array
		 */
		public function tracking_DataCollect( $aData ) {
			$aData = parent::tracking_DataCollect( $aData );
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeature();
			$sSlug = $oFO->getFeatureSlug();
			if ( empty( $aData[ $sSlug ][ 'options' ][ 'unique_installation_id' ] ) ) {
				$aData[ $sSlug ][ 'options' ][ 'unique_installation_id' ] = $oFO->getPluginInstallationId();
			}
			return $aData;
		}

		/**
		 * @param array $aAttrs
		 * @return bool
		 */
		protected function getIfDisplayAdminNotice( $aAttrs ) {

			if ( ! parent::getIfDisplayAdminNotice( $aAttrs ) ) {
				return false;
			}
			if ( isset( $aAttrs[ 'delay_days'] ) && is_int( $aAttrs[ 'delay_days'] )
				 && ( $this->getInstallationDays() < $aAttrs[ 'delay_days'] ) ) {
				return false;
			}
			return true;
		}

		/**
		 * @param $aAttr
		 * @throws Exception
		 */
		public function addNotice_rate_plugin( $aAttr ) {

			$aRenderData = array(
				'notice_attributes' => $aAttr,
				'strings' => array(
					'dismiss' => _wpsf__( "I'd rather not show this support" ).' / '._wpsf__( "I've done this already" ).' :D',
					'forums' => __( 'Support Forums' )
				),
				'hrefs' => array(
					'forums' => 'https://wordpress.org/support/plugin/wp-simple-firewall',
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}

		/**
		 * @param array $aNoticeAttributes
		 * @throws Exception
		 */
		public function addNotice_wizard_welcome( $aNoticeAttributes ) {

			$sWizardUrl = add_query_arg(
				array(
					'shield_action' => 'wizard',
					'wizard' => 'welcome',
				),
				$this->loadWp()->getUrl_WpAdmin()
			);


			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings' => array(
					'dismiss' => _wpsf__( "I don't need the setup wizard just now" ),
				),
				'hrefs' => array(
					'wizard' => $sWizardUrl,
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}

		/**
		 * @see autoAddToAdminNotices()
		 * @param array $aAttr
		 * @throws Exception
		 */
		protected function addNotice_php53_version_warning( $aAttr ) {
			$oDp = $this->loadDataProcessor();
			if ( $oDp->getPhpVersionIsAtLeast( '5.3.2' ) ) {
				return;
			}

			$oCon = $this->getController();
			$aRenderData = array(
				'notice_attributes' => $aAttr,
				'strings' => array(
					'your_php_version' => sprintf( _wpsf__( 'Your PHP version is very (10+ years) old: %s' ), $oDp->getPhpVersion() ),
					'future_versions_not_supported' => sprintf( _wpsf__( 'Future versions of the %s plugin will not support your PHP version.' ), $oCon->getHumanName() ),
					'ask_host_to_upgrade' => sprintf( _wpsf__( 'You should ask your host to upgrade or provide a much newer PHP version.' ), $oCon->getHumanName() ),
					'any_questions' => sprintf( _wpsf__( 'If you have any questions, please leave us a message in the forums.' ), $oCon->getHumanName() ),
					'dismiss' => _wpsf__( 'Dismiss this notice' ),
					'forums' => __( 'Support Forums' )
				),
				'hrefs' => array(
					'forums' => 'https://wordpress.org/support/plugin/wp-simple-firewall',
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}

		/**
		 * @see autoAddToAdminNotices()
		 * @param array $aNoticeAttributes
		 */
		protected function addNotice_plugin_update_available( $aNoticeAttributes ) {
			$oFO = $this->getFeature();
			$oWpUsers = $this->loadWpUsers();

			$sAdminNoticeMetaKey = $oFO->prefix( 'plugin-update-available' );
			if ( $this->loadAdminNoticesProcessor()->getAdminNoticeIsDismissed( 'plugin-update-available' ) ) {
				$oWpUsers->updateUserMeta( $sAdminNoticeMetaKey, $oFO->getVersion() ); // so they've hidden it. Now we set the current version so it doesn't display below
				return;
			}

			if ( !$this->getIfShowAdminNotices() ) {
				return;
			}

			$oWp = $this->loadWp();
			$oWpPlugins = $this->loadWpPlugins();
			$sBaseFile = $this->getController()->getPluginBaseFile();
			if ( !$oWp->getIsPage_Updates() && $oWpPlugins->isUpdateAvailable( $sBaseFile ) ) { // Don't show on the update page
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
					'render_slug' => 'plugin-update-available',
					'strings' => array(
						'plugin_update_available' => sprintf( _wpsf__( 'There is an update available for the "%s" plugin.' ), $this->getController()->getHumanName() ),
						'click_update' => _wpsf__( 'Please click to update immediately' ),
						'dismiss' => _wpsf__( 'Dismiss this notice' )
					),
					'hrefs' => array(
						'upgrade_link' =>  $oWpPlugins->getLinkPluginUpgrade( $sBaseFile )
					)
				);
				$this->insertAdminNotice( $aRenderData );
			}
		}

		/**
		 * @see autoAddToAdminNotices()
		 * @param array $aNoticeAttributes
		 */
		protected function addNotice_translate_plugin( $aNoticeAttributes ) {

			if ( $this->getIfShowAdminNotices() ) {
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
					'strings' => array(
						'like_to_help' => sprintf( _wpsf__( "Would you like to help translate the %s plugin into your language?" ), $this->getController()->getHumanName() ),
						'head_over_to' => sprintf( _wpsf__( 'Head over to: %s' ), '' ),
						'site_url' => 'translate.icontrolwp.com',
						'dismiss' => _wpsf__( 'Dismiss this notice' )
					),
					'hrefs' => array(
						'translate' => 'http://translate.icontrolwp.com'
					)
				);
				$this->insertAdminNotice( $aRenderData );
			}
		}

		/**
		 * @return bool
		 */
		public function getIsDeleteOnDeactivate() {
			return $this->getFeature()->getOptIs( 'delete_on_deactivate', 'Y' );
		}

		/**
		 * @param bool $bShow
		 * @return bool
		 */
		public function getIsShowMarketing( $bShow ) {
			if ( !$bShow ) {
				return $bShow;
			}

			if ( $this->getInstallationDays() < 1 ) {
				$bShow = false;
			}

			$oWpFunctions = $this->loadWp();
			if ( class_exists( 'Worpit_Plugin' ) ) {
				if ( method_exists( 'Worpit_Plugin', 'IsLinked' ) ) {
					$bShow = !Worpit_Plugin::IsLinked();
				}
				else if ( $oWpFunctions->getOption( Worpit_Plugin::$VariablePrefix.'assigned' ) == 'Y'
				          && $oWpFunctions->getOption( Worpit_Plugin::$VariablePrefix.'assigned_to' ) != '' ) {

					$bShow = false;
				}
			}
			return $bShow;
		}

		/**
		 * @return bool
		 */
		protected function getIfShowAdminNotices() {
			return $this->getFeature()->getOptIs( 'enable_upgrade_admin_notice', 'Y' );
		}
	}

endif;