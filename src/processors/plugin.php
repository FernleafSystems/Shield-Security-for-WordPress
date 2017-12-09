<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_plugin.php' );

class ICWP_WPSF_Processor_Plugin extends ICWP_WPSF_Processor_BasePlugin {

	/**
	 * @var ICWP_WPSF_Processor_Plugin_SetupWizard
	 */
	protected $oSetupWizardProcessor;

	/**
	 * @var ICWP_WPSF_Processor_Plugin_Badge
	 */
	protected $oBadgeProcessor;

	/**
	 * @var ICWP_WPSF_Processor_Plugin_Tracking
	 */
	protected $oTrackingProcessor;

	/**
	 */
	public function run() {
		parent::run();
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();

		$this->removePluginConflicts();
		$this->getBadgeProcessor()
			 ->run();

		if ( $oFO->isTrackingEnabled() || !$oFO->isTrackingPermissionSet() ) {
			$this->getTrackingProcessor()->run();
		}

		add_action( 'wp_loaded', array( $this, 'onWpLoaded' ) );
		add_action( 'in_admin_footer', array( $this, 'printVisitorIpFooter' ) );

		$sAction = $this->loadDataProcessor()->FetchGet( 'shield_action', '' );
		switch ( $sAction ) {
			case 'dump_tracking_data':
				add_action( 'wp_loaded', array( $this, 'dumpTrackingData' ) );
				break;
		}

		// TODO: Wrap up
		if ( isset( $_GET[ 'wizard' ] ) && $this->loadDP()->getPhpVersionIsAtLeast( 5.4 ) ) {
			$this->getSetupWizardProcessor()->run();
		}
	}

	public function onWpLoaded() {
		if ( $this->getController()->getIsValidAdminArea() ) {
			$this->maintainPluginLoadPosition();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Badge
	 */
	protected function getBadgeProcessor() {
		if ( !isset( $this->oBadgeProcessor ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'plugin_badge.php' );
			$this->oBadgeProcessor = new ICWP_WPSF_Processor_Plugin_Badge( $this->getFeature() );
		}
		return $this->oBadgeProcessor;
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_SetupWizard
	 */
	public function getSetupWizardProcessor() {
		if ( !isset( $this->oSetupWizardProcessor ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'plugin_setupwizard.php' );
			$this->oSetupWizardProcessor = new ICWP_WPSF_Processor_Plugin_SetupWizard( $this->getFeature() );
		}
		return $this->oSetupWizardProcessor;
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Tracking
	 */
	protected function getTrackingProcessor() {
		if ( !isset( $this->oTrackingProcessor ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'plugin_tracking.php' );
			$this->oTrackingProcessor = new ICWP_WPSF_Processor_Plugin_Tracking( $this->getFeature() );
		}
		return $this->oTrackingProcessor;
	}

	/**
	 */
	public function dumpTrackingData() {
		if ( $this->getController()->getIsValidAdminArea() ) {
			echo sprintf( '<pre><code>%s</code></pre>', print_r( $this->getTrackingProcessor()
																	  ->collectTrackingData(), true ) );
			die();
		}
	}

	/**
	 */
	public function printTrackingDataBox() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();

		if ( !$this->getController()->getIsValidAdminArea() ) {
			return;
		}

		$aRenderData = array(
			'strings'     => array(
				'tracking_data' => print_r( $this->getTrackingProcessor()->collectTrackingData(), true ),
			),
			//				'sAjaxNonce' => wp_create_nonce( 'icwp_ajax' ),
			'js_snippets' => array(//					'options_to_restrict' => "'".implode( "','", $oFO->getOptionsToRestrict() )."'",
			)
		);
		add_thickbox();
		echo $oFO->renderTemplate( 'snippets'.DIRECTORY_SEPARATOR.'plugin_tracking_data_dump.php', $aRenderData );
	}

	/**
	 * Sets this plugin to be the first loaded of all the plugins.
	 */
	protected function maintainPluginLoadPosition() {
		$oWpPlugins = $this->loadWpPlugins();
		$sBaseFile = $this->getController()->getPluginBaseFile();
		$nLoadPosition = $oWpPlugins->getActivePluginLoadPosition( $sBaseFile );
		if ( $nLoadPosition !== 0 && $nLoadPosition > 0 ) {
			$oWpPlugins->setActivePluginLoadFirst( $sBaseFile );
		}
	}

	public function printVisitorIpFooter() {
		if ( apply_filters( 'icwp_wpsf_print_admin_ip_footer', true ) ) {
			echo sprintf( '<p><span>%s</span></p>', sprintf( _wpsf__( 'Your IP address is: %s' ), $this->ip() ) );
		}
	}

	/**
	 * @see autoAddToAdminNotices()
	 * @param array $aNoticeAttributes
	 */
	protected function addNotice_override_forceoff( $aNoticeAttributes ) {

		if ( $this->getController()->getIfForceOffActive() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'message'   => sprintf( _wpsf__( 'Warning - %s' ), sprintf( _wpsf__( '%s is not currently running' ), $this->getController()
																															   ->getHumanName() ) ),
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
				'strings'           => array(
					'yes'          => "Yes please! I'd love to join in and learn more",
					'no'           => "No thanks, I'm not interested in such groups",
					'we_dont_spam' => "( Fear not! SPAM is for losers. And we're not losers! )",
					'your_name'    => _wpsf__( 'Your Name' ),
					'your_email'   => _wpsf__( 'Your Email' ),
					'dismiss'      => "No thanks, I'm not interested in such informative groups",
					'summary'      => 'The Shield security team is running an initiative (with currently 2000+ members) to raise awareness of WordPress Security
				and to provide further help with the Shield security plugin. Get Involved here:',
				),
				'hrefs'             => array(
					'form_action' => '//hostliketoast.us2.list-manage.com/subscribe/post?u=e736870223389e44fb8915c9a&id=0e1d527259'
				),
				'install_days'      => $nDays
			);
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * Lets you remove certain plugin conflicts that might interfere with this plugin
	 */
	protected function removePluginConflicts() {
		if ( class_exists( 'AIO_WP_Security' ) && isset( $GLOBALS[ 'aio_wp_security' ] ) ) {
			remove_action( 'init', array( $GLOBALS[ 'aio_wp_security' ], 'wp_security_plugin_init' ), 0 );
		}
	}
}