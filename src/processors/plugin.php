<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin extends ICWP_WPSF_Processor_BasePlugin {

	/**
	 */
	public function run() {
		parent::run();
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$this->getSubProCronDaily()
			 ->run();
		$this->getSubProCronHourly()
			 ->run();

		$this->removePluginConflicts();
		$this->getSubProBadge()
			 ->run();

		if ( $oFO->isTrackingEnabled() || !$oFO->isTrackingPermissionSet() ) {
			$this->getSubProTracking()->run();
		}

		if ( $oFO->isImportExportPermitted() ) {
			$this->getSubProImportExport()->run();
		}

		switch ( $this->loadRequest()->query( 'shield_action', '' ) ) {
			case 'dump_tracking_data':
				add_action( 'wp_loaded', array( $this, 'dumpTrackingData' ) );
				break;

			case 'importexport_export':
			case 'importexport_import':
			case 'importexport_handshake':
			case 'importexport_updatenotified':
				if ( $oFO->isImportExportPermitted() ) {
					add_action( 'init', [ $this->getSubProImportExport(), 'runAction' ] );
				}
				break;
			default:
				break;
		}

		add_action( 'admin_footer', array( $this, 'printAdminFooterItems' ), 100, 0 );
	}

	public function onWpLoaded() {
		if ( $this->getCon()->isValidAdminArea() ) {
			$this->maintainPluginLoadPosition();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Badge|mixed
	 */
	protected function getSubProBadge() {
		return $this->getSubPro( 'badge' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_CronDaily|mixed
	 */
	protected function getSubProCronDaily() {
		return $this->getSubPro( 'crondaily' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_CronHourly|mixed
	 */
	protected function getSubProCronHourly() {
		return $this->getSubPro( 'cronhourly' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Tracking|mixed
	 */
	protected function getSubProTracking() {
		return $this->getSubPro( 'tracking' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_ImportExport|mixed
	 */
	public function getSubProImportExport() {
		return $this->getSubPro( 'importexport' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Notes|mixed
	 */
	public function getSubProcessorNotes() {
		return $this->getSubPro( 'notes' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'badge'        => 'ICWP_WPSF_Processor_Plugin_Badge',
			'importexport' => 'ICWP_WPSF_Processor_Plugin_ImportExport',
			'notes'        => 'ICWP_WPSF_Processor_Plugin_Notes',
			'tracking'     => 'ICWP_WPSF_Processor_Plugin_Tracking',
			'crondaily'    => 'ICWP_WPSF_Processor_Plugin_CronDaily',
			'cronhourly'   => 'ICWP_WPSF_Processor_Plugin_CronHourly',
		];
	}

	public function printAdminFooterItems() {
		$this->printPluginDeactivateSurvey();
		$this->printToastTemplate();
	}

	/**
	 * Sets this plugin to be the first loaded of all the plugins.
	 */
	private function printToastTemplate() {
		if ( $this->getCon()->isModulePage() ) {
			$aRenderData = array(
				'strings'     => array(
					'title' => $this->getCon()->getHumanName(),
				),
				'js_snippets' => array()
			);
			echo $this->getMod()
					  ->renderTemplate( 'snippets/toaster.twig', $aRenderData, true );
		}
	}

	private function printPluginDeactivateSurvey() {
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
		if ( $this->getCon()->isPluginAdmin() ) {
			echo sprintf( '<pre><code>%s</code></pre>', print_r( $this->getSubProTracking()
																	  ->collectTrackingData(), true ) );
			die();
		}
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
		$sBaseFile = $this->getCon()->getPluginBaseFile();
		$nLoadPosition = $oWpPlugins->getActivePluginLoadPosition( $sBaseFile );
		if ( $nLoadPosition !== 0 && $nLoadPosition > 0 ) {
			$oWpPlugins->setActivePluginLoadFirst( $sBaseFile );
		}
	}

	/**
	 * @see autoAddToAdminNotices()
	 * @param array $aNoticeAttributes
	 */
	protected function addNotice_override_forceoff( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		$oCon = $this->getCon();
		if ( $oCon->getIfForceOffActive() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'title'   => sprintf( '%s: %s', _wpsf__( 'Warning' ), sprintf( _wpsf__( '%s is not protecting your site' ), $oCon->getHumanName() ) ),
					'message' => sprintf(
						_wpsf__( 'Please delete the "%s" file to reactivate %s protection' ),
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
		$sName = $this->getCon()->getHumanName();

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

	/**
	 * unused
	 * @deprecated v7
	 */
	public function printVisitorIpFooter() {
		if ( apply_filters( 'icwp_wpsf_print_admin_ip_footer', true ) ) {
			echo sprintf( '<p><span>%s</span></p>', sprintf( _wpsf__( 'Your IP address is: %s' ), $this->ip() ) );
		}
	}
}