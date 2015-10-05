<?php

if ( !class_exists( 'ICWP_WPSF_Processor_AdminAccessRestriction', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_AdminAccessRestriction extends ICWP_WPSF_Processor_Base {

		/**
		 * @var string
		 */
		protected $sOptionRegexPattern;

		public function run() {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();
			$oWp = $this->loadWpFunctionsProcessor();

			add_filter( $oFO->doPluginPrefix( 'has_permission_to_submit' ), array( $oFO, 'doCheckHasPermissionToSubmit' ) );
			add_filter( $oFO->doPluginPrefix( 'has_permission_to_view' ), array( $oFO, 'doCheckHasPermissionToSubmit' ) );
			if ( ! $oFO->getIsUpgrading() && ! $oWp->getIsLoginRequest() ) {
				add_filter( 'pre_update_option', array( $this, 'blockOptionsSaves' ), 1, 3 );
			}

			$aPluginRestrictions = $oFO->getAdminAccessArea_Plugins();
			if ( !empty( $aPluginRestrictions ) ) {
				add_filter( 'user_has_cap', array( $this, 'disablePluginManipulation' ), 0, 3 );
			}

			$aThemeRestrictions = $oFO->getAdminAccessArea_Themes();
			if ( !empty( $aThemeRestrictions ) ) {
				add_filter( 'user_has_cap', array( $this, 'disableThemeManipulation' ), 0, 3 );
			}

			$aPostRestrictions = $oFO->getAdminAccessArea_Posts();
			if ( !empty( $aPostRestrictions ) ) {
				add_filter( 'user_has_cap', array( $this, 'disablePostsManipulation' ), 0, 3 );
			}

			add_action( 'admin_footer', array( $this, 'printAdminAccessAjaxForm' ) );
		}

		/**
		 * @param array $aNoticeAttributes
		 */
		public function addNotice_certain_options_restricted( $aNoticeAttributes ) {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();
			if ( $oFO->doCheckHasPermissionToSubmit() ) {
				return;
			}

			$sCurrentPage = $this->loadWpFunctionsProcessor()->getCurrentPage();
			if ( !in_array( $sCurrentPage, $oFO->getOptionsPagesToRestrict() ) ) {
				return;
			}

			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings' => array(
					'notice_message' => _wpsf__( 'Altering certain options has been restricted by your WordPress security administrator.' )
						.' '._wpsf__( 'Repeated failed attempts to authenticate will probably lock you out of this site.' )
				),
				'hrefs' => array(
					'setting_page' => sprintf(
						'<a href="%s" title="%s">%s</a>',
						$oFO->getFeatureAdminPageUrl(),
						_wpsf__( 'Admin Access Login' ),
						sprintf( _wpsf__('Go here to manage settings and authenticate with the %s plugin.'), $this->getController()->getHumanName() )
					)
				)
			);
			add_thickbox();
			$this->insertAdminNotice( $aRenderData );
		}


		/**
		 * Right before a plugin option is due to update it will check that we have permissions to do so and if not, will
		 * revert the option to save to the previous one.
		 *
		 * @param mixed $mNewOptionValue
		 * @param string $sOptionKey
		 * @param mixed $mOldValue
		 * @return mixed
		 */
		public function blockOptionsSaves( $mNewOptionValue, $sOptionKey, $mOldValue ) {
			if ( !$this->getIsOptionKeyForThisPlugin( $sOptionKey ) ) {
				// Now we test certain other options saving based on where it's restricted
				if ( !$this->getIsSavingOptionRestricted( $sOptionKey ) ) {
					return $mNewOptionValue;
				}
			}

			$fHasPermissionToChangeOptions = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'has_permission_to_submit' ), true );
			if ( !$fHasPermissionToChangeOptions ) {
//				$sAuditMessage = sprintf( _wpsf__('Attempt to save/update option "%s" was blocked.'), $sOption );
//			    $this->addToAuditEntry( $sAuditMessage, 3, 'admin_access_option_block' );
				return $mOldValue;
			}

			return $mNewOptionValue;
		}

		/**
		 * @param string $sOptionKey
		 * @return int
		 */
		protected function getIsOptionKeyForThisPlugin( $sOptionKey ) {
			return preg_match( $this->getOptionRegexPattern(), $sOptionKey );
		}

		/**
		 * @param string $sOptionKey
		 * @return int
		 */
		protected function getIsSavingOptionRestricted( $sOptionKey ) {
			$bRestricted = false;
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();
			if ( $oFO->getAdminAccessArea_Options() ) {
				$bRestricted = in_array(
					$sOptionKey,
					$oFO->getOptionsToRestrict()
				);
			}
			return $bRestricted;
		}

		/**
		 * @param array $aAllCaps
		 * @param $cap
		 * @param array $aArgs
		 * @return array
		 */
		public function disablePluginManipulation( $aAllCaps, $cap, $aArgs ) {
			// If we're registered with Admin Access we can do everything!
			$bHasAdminAccess = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'has_permission_to_submit' ), true );
			if ( $bHasAdminAccess ) {
				return $aAllCaps;
			}

			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();

			/** @var string $sRequestedCapability */
			$sRequestedCapability = $aArgs[0];
			$aEditCapabilities = array( 'activate_plugins', 'delete_plugins', 'install_plugins', 'update_plugins' );

			if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
				$aAreaRestrictions = $oFO->getAdminAccessArea_Plugins();
				if ( in_array( $sRequestedCapability, $aAreaRestrictions ) ) {
					$aAllCaps[ $sRequestedCapability ] = false;
				}
			}

			return $aAllCaps;
		}

		/**
		 * @param array $aAllCaps
		 * @param $cap
		 * @param array $aArgs
		 * @return array
		 */
		public function disableThemeManipulation( $aAllCaps, $cap, $aArgs ) {
			// If we're registered with Admin Access we don't modify anything
			$bHasAdminAccess = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'has_permission_to_submit' ), true );
			if ( $bHasAdminAccess ) {
				return $aAllCaps;
			}

			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();

			/** @var string $sRequestedCapability */
			$sRequestedCapability = $aArgs[0];
			$aEditCapabilities = array( 'switch_themes', 'edit_theme_options', 'install_themes', 'update_themes', 'delete_themes' );

			if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
				$aAreaRestrictions = $oFO->getAdminAccessArea_Themes();
				if ( in_array( $sRequestedCapability, $aAreaRestrictions ) ) {
					$aAllCaps[ $sRequestedCapability ] = false;
				}
			}

			return $aAllCaps;
		}

		/**
		 * @param array $aAllCaps
		 * @param $cap
		 * @param array $aArgs
		 * @return array
		 */
		public function disablePostsManipulation( $aAllCaps, $cap, $aArgs ) {
			// If we're registered with Admin Access we don't modify anything
			$bHasAdminAccess = apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'has_permission_to_submit' ), true );
			if ( $bHasAdminAccess ) {
				return $aAllCaps;
			}

			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();

			/** @var string $sRequestedCapability */
			$sRequestedCapability = $aArgs[0];
			$aEditCapabilities = array(
				'edit_post', 'publish_post', 'delete_post',
				'edit_posts', 'publish_posts', 'delete_posts',
				'edit_page', 'publish_page', 'delete_page',
				'edit_pages', 'publish_pages', 'delete_pages'
			);
			if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
				$sRequestedCapabilityTrimmed = str_replace( array( '_posts', '_pages', '_post', '_page' ), '', $sRequestedCapability ); //Order of items in this array is important!
				$aAreaRestrictions = $oFO->getAdminAccessArea_Posts();
				if ( in_array( $sRequestedCapabilityTrimmed, $aAreaRestrictions ) ) {
					$aAllCaps[ $sRequestedCapability ] = false;
				}
			}
			return $aAllCaps;
		}

		/**
		 * @return string
		 */
		protected function getOptionRegexPattern() {
			if ( !isset( $this->sOptionRegexPattern ) ) {
				$this->sOptionRegexPattern = '/^'. $this->getFeatureOptions()->getOptionStoragePrefix() . '.*_options$/';
			}
			return $this->sOptionRegexPattern;
		}

		public function printAdminAccessAjaxForm() {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getFeatureOptions();

			if ( $oFO->doCheckHasPermissionToSubmit() ) {
				return;
			}

			$aRenderData = array(
				'strings' => array(
					'editing_restricted' => _wpsf__( 'Editing this option is currently restricted.' ),
					'unlock_link' => sprintf(
						'<a href="%s" title="%s" class="thickbox">%s</a>',
						'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
						_wpsf__( 'Admin Access Login' ),
						_wpsf__('Unlock')
					),
				),
				'sAjaxNonce' => wp_create_nonce( 'icwp_ajax' ),
				'js_snippets' => array(
					'options_to_restrict' => "'".implode( "','", $oFO->getOptionsToRestrict() )."'",
				)
			);
			add_thickbox();
			echo $oFO->renderTemplate( 'snippets'.ICWP_DS.'admin_access_login_box.php', $aRenderData );
		}
	}

endif;
