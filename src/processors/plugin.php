<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Plugin', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_plugin.php' );

	class ICWP_WPSF_Processor_Plugin extends ICWP_WPSF_Processor_BasePlugin {

		/**
		 */
		public function run() {
			parent::run();

			$this->toggleForceOff();
			$this->removePluginConflicts();

			if ( $this->getIsOption( 'display_plugin_badge', 'Y' ) ) {
				add_action( 'wp_footer', array( $this, 'printPluginBadge' ) );
			}

			add_action( 'widgets_init', array( $this, 'addPluginBadgeWidget' ) );
			add_action( 'in_admin_footer', array( $this, 'printVisitorIpFooter' ) );

			if ( $this->getController()->getIsValidAdminArea() ) {
				$this->maintainPluginLoadPosition();
			}

			add_filter( $this->getFeatureOptions()->doPluginPrefix( 'dashboard_widget_content' ), array( $this, 'gatherPluginWidgetContent' ), 100 );
		}

		/**
		 * @param array $aContent
		 * @return array
		 */
		public function gatherPluginWidgetContent( $aContent ) {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			$oCon = $this->getController();

			$sFooter = sprintf( _wpsf__( '%s is provided by %s' ), $oCon->getHumanName(), sprintf( '<a href="%s">iControlWP</a>', 'http://icwp.io/7f' )  );
			$aDisplayData = array(
				'sInstallationDays' => sprintf( _wpsf__( 'Days Installed: %s' ), $this->getInstallationDays() ),
				'sFooter' => $sFooter,
				'sIpAddress' =>	sprintf( _wpsf__( 'Your IP address is: %s' ), $this->human_ip() )
			);

			if ( !is_array( $aContent ) ) {
				$aContent = array();
			}
			$aContent[] = $oFO->renderTemplate( 'snippets/widget_dashboard_plugin.php', $aDisplayData );
			return $aContent;
		}

		protected function toggleForceOff() {
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
			$oFO = $this->getFeatureOptions();
			$sForceOff = $this->loadDataProcessor()->FetchGet( 'shield_forceoff', '' );
			if ( !empty( $sForceOff ) ) {
				if ( $sForceOff == $oFO->getPluginInstallationId() ) {
					$oFs = $this->loadFileSystemProcessor();
					$oCon = $this->getController();
					$sPath = $oCon->getRootDir() . 'forceOff';
					if ( $oCon->getIfOverrideOff() ) {
						$oFs->deleteFile( $sPath );
					}
					else {
						$oFs->touch( $sPath );
					}
					$this->loadWpFunctionsProcessor()->redirectToAdmin();
				}
				else {
					add_filter( $this->getFeatureOptions()->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
				}
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
			require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'plugin_badgewidget.php' );
			ICWP_WPSF_Processor_Plugin_BadgeWidget::SetFeatureOptions( $this->getFeatureOptions() );
			register_widget( 'ICWP_WPSF_Processor_Plugin_BadgeWidget' );
		}

		public function printPluginBadge() {
			$oCon = $this->getController();
			$oRender = $this->loadRenderer( $oCon->getPath_Templates().'html' );
			$sContents = $oRender
				->clearRenderVars()
				->setTemplate( 'plugin_badge' )
				->setTemplateEngineHtml()
				->render();
			$sBadgeText = sprintf(
				_wpsf__( 'This Site Is Protected By %s' ),
				sprintf(
					'<br /><span style="font-weight: bold;">The %s &rarr;</span>',
					$oCon->getHumanName()
				)
			);
			$sBadgeText = apply_filters( 'icwp_shield_plugin_badge_text', $sBadgeText );
			echo sprintf( $sContents, $oCon->getPluginUrl_Image( 'pluginlogo_32x32.png' ), $oCon->getHumanName(), $sBadgeText );
		}

		public function printVisitorIpFooter() {
			if ( apply_filters( 'icwp_wpsf_print_admin_ip_footer', true ) ) {
				echo sprintf( '<p><span>%s</span></p>', sprintf( _wpsf__( 'Your IP address is: %s' ), $this->human_ip() ) );
			}
		}

		/**
		 * @see autoAddToAdminNotices()
		 * @param array $aNoticeAttributes
		 */
		protected function addNotice_override_forceoff( $aNoticeAttributes ) {

			if ( $this->getController()->getIfOverrideOff() ) {
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
					'strings' => array(
						'message' => sprintf( _wpsf__( 'Warning - %s' ), sprintf( _wpsf__( '%s is not currently running' ), $this->getController()->getHumanName() ) ),
						'force_off' => sprintf( _wpsf__( 'Please delete the "%s" file to reactivate the Firewall processing' ), 'forceOff' )
					)
				);
				$this->insertAdminNotice( $aRenderData );
			}
		}

		/**
		 * @see autoAddToAdminNotices()
		 * @param array $aNoticeAttributes
		 */
		protected function addNotice_plugin_mailing_list_signup( $aNoticeAttributes ) {

			$nDays = $this->getInstallationDays();
			if ( $this->getIfShowAdminNotices() && $nDays >= 5 ) {
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
					'strings' => array(
						'yes' => "Yes please! I'd love to join in and learn more",
						'no' => "No thanks, I'm not interested in such groups",
						'we_dont_spam' => "( Fear not! SPAM is for losers. And we're not losers! )",
						'your_name' => _wpsf__( 'Your Name' ),
						'your_email' => _wpsf__( 'Your Email' ),
						'dismiss' => "No thanks, I'm not interested in such informative groups",
						'summary' => 'The Shield security team is running an initiative (with currently 2000+ members) to raise awareness of WordPress Security
				and to provide further help with the Shield security plugin. Get Involved here:',
					),
					'hrefs' => array(
						'form_action' => '//hostliketoast.us2.list-manage.com/subscribe/post?u=e736870223389e44fb8915c9a&id=0e1d527259'
					),
					'install_days' => $nDays
				);
				$this->insertAdminNotice( $aRenderData );
			}
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