<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_plugin.php' );

class ICWP_WPSF_Processor_Plugin extends ICWP_WPSF_Processor_BasePlugin {

	use \FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;

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
		$oFO = $this->getMod();
		$this->setupCron();

		$this->removePluginConflicts();
		$this->getBadgeProcessor()
			 ->run();

		if ( $oFO->isTrackingEnabled() || !$oFO->isTrackingPermissionSet() ) {
			$this->getTrackingProcessor()->run();
		}

		if ( $oFO->isImportExportPermitted() ) {
			$this->getSubProcessorImportExport()->run();
		}

		add_action( 'wp_loaded', array( $this, 'onWpLoaded' ) );
		add_action( 'in_admin_footer', array( $this, 'printVisitorIpFooter' ) );

		switch ( $this->loadRequest()->query( 'shield_action', '' ) ) {
			case 'dump_tracking_data':
				add_action( 'wp_loaded', array( $this, 'dumpTrackingData' ) );
				break;

			case 'importexport_export':
			case 'importexport_handshake':
			case 'importexport_updatenotified':
				if ( $oFO->isImportExportPermitted() ) {
					$this->getSubProcessorImportExport()->runAction();
				}
				break;
			default:
				break;
		}

		add_action( 'admin_footer', array( $this, 'printPluginDeactivateSurvey' ), 100, 0 );
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	protected function getCronName() {
		return $this->getMod()->prefix( 'daily' );
	}

	/**
	 * Use the included action to hook into the plugin's daily cron
	 */
	public function runCron() {
		do_action( $this->getMod()->prefix( 'daily-cron' ) );
	}

	public function onWpLoaded() {
		if ( $this->getController()->isValidAdminArea() ) {
			$this->maintainPluginLoadPosition();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Badge
	 */
	protected function getBadgeProcessor() {
		if ( !isset( $this->oBadgeProcessor ) ) {
			require_once( dirname( __FILE__ ).'/plugin_badge.php' );
			$this->oBadgeProcessor = new ICWP_WPSF_Processor_Plugin_Badge( $this->getMod() );
		}
		return $this->oBadgeProcessor;
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Tracking
	 */
	protected function getTrackingProcessor() {
		if ( !isset( $this->oTrackingProcessor ) ) {
			require_once( dirname( __FILE__ ).'/plugin_tracking.php' );
			$this->oTrackingProcessor = new ICWP_WPSF_Processor_Plugin_Tracking( $this->getMod() );
		}
		return $this->oTrackingProcessor;
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_ImportExport
	 */
	public function getSubProcessorImportExport() {
		$oProc = $this->getSubPro( 'importexport' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/plugin_importexport.php' );
			$oProc = new ICWP_WPSF_Processor_Plugin_ImportExport( $this->getMod() );
			$this->aSubPros[ 'importexport' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Notes
	 */
	public function getSubProcessorNotes() {
		$oProc = $this->getSubPro( 'notes' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/plugin_notes.php' );
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oMod */
			$oMod = $this->getMod();
			$oProc = new ICWP_WPSF_Processor_Plugin_Notes( $oMod );
			$this->aSubPros[ 'notes' ] = $oProc;
		}
		return $oProc;
	}

	public function printPluginDeactivateSurvey() {
		$oWp = $this->loadWp();
		if ( $oWp->isCurrentPage( 'plugins.php' ) ) {

			$aOpts = array(
				'reason_confusing'   => "It's too confusing",
				'reason_expected'    => "It's not what I expected",
				'reason_accident'    => "I downloaded it accidentally",
				'reason_alternative' => "I'm already using an alternative",
				'reason_trust'       => "I don't trust the developer :(",
				'reason_not_work'    => "It doesn't work",
				'reason_errors'      => "I'm getting errors",
			);

			$aRenderData = array(
				'strings'     => array(
					'editing_restricted' => _wpsf__( 'Editing this option is currently restricted.' ),
				),
				'inputs'      => array(
					'checkboxes' => $this->loadDP()->shuffleArray( $aOpts )
				),
				'js_snippets' => array()
			);
			echo $this->getMod()
					  ->renderTemplate( 'snippets/plugin-deactivate-survey.php', $aRenderData );
		}
	}

	/**
	 */
	public function dumpTrackingData() {
		if ( $this->getController()->isValidAdminArea() ) {
			echo sprintf( '<pre><code>%s</code></pre>', print_r( $this->getTrackingProcessor()
																	  ->collectTrackingData(), true ) );
			die();
		}
	}

	/**
	 */
	public function printTrackingDataBox() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		if ( !$this->getController()->isValidAdminArea() ) {
			return;
		}

		$aRenderData = array(
			'strings'     => array(
				'tracking_data' => print_r( $this->getTrackingProcessor()->collectTrackingData(), true ),
			),
			'js_snippets' => array()
		);
		add_thickbox();
		echo $oFO->renderTemplate( 'snippets/plugin_tracking_data_dump.php', $aRenderData );
	}

	public function runDailyCron() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oFO->updateTestCronLastRunAt();
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
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		$oCon = $this->getController();
		if ( $oCon->getIfForceOffActive() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'title'   => sprintf( '%s - %s', _wpsf__( 'Warning' ), sprintf( _wpsf__( '%s plugin is not currently processing requests' ), $oCon->getHumanName() ) ),
					'message' => sprintf(
						_wpsf__( 'Please delete the "%s" file to reactivate the %s protection' ),
						'forceOff',
						$oCon->getHumanName()
					),
					'delete'  => _wpsf__( 'Click here to automatically delete the file' )
				),
				'ajax'              => array(
					'delete_forceoff' => $oFO->getAjaxActionData( 'delete_forceoff', true )
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
		$oModCon = $this->getMod();
		$sName = $this->getController()->getHumanName();

		$nDays = $this->getInstallationDays();
		if ( $this->getIfShowAdminNotices() && $nDays >= 5 ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'title'        => 'Join Us!',
					'yes'          => "Yes please! I'd love to join in and learn more",
					'no'           => "No thanks, I'm not interested in such groups",
					'we_dont_spam' => "( Fear not! SPAM is for losers. And we're not losers! )",
					'your_name'    => _wpsf__( 'Your Name' ),
					'your_email'   => _wpsf__( 'Your Email' ),
					'dismiss'      => "No thanks, I'm not interested in such informative groups",
					'summary'      => sprintf( 'The %s security team is running an initiative (with currently 3000+ members) to raise awareness of WordPress Security
				and to provide further help with the %s security plugin. Get Involved here:', $sName, $sName ),
				),
				'hrefs'             => array(
					'form_action'    => '//hostliketoast.us2.list-manage.com/subscribe/post?u=e736870223389e44fb8915c9a&id=0e1d527259',
					'privacy_policy' => $oModCon->getDef( 'href_privacy_policy' )
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