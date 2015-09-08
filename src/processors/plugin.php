<?php

if ( !class_exists( 'ICWP_WPSF_Processor_Plugin', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base_plugin.php' );

	class ICWP_WPSF_Processor_Plugin extends ICWP_WPSF_Processor_BasePlugin {

		/**
		 */
		public function run() {
			parent::run();

			$this->removePluginConflicts();

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
		 * @see autoAddToAdminNotices()
		 * @param array $aNoticeAttributes
		 */
		protected function addNotice_override_forceoff( $aNoticeAttributes ) {

			if ( $this->getFeatureOptions()->getIfOverrideOff() ) {
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
					'notice_classes' => 'error',
					'strings' => array(
						'message' => sprintf( _wpsf__( 'Warning - %s.' ), sprintf( _wpsf__( '%s is not currently running' ), $this->getController()->getHumanName() ) ),
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
			$oFO = $this->getFeatureOptions();

			$nDays = $this->getInstallationDays();
			if ( $this->getIfShowAdminNotices() && $nDays >= 5 ) {
				$aRenderData = array(
					'notice_attributes' => $aNoticeAttributes,
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