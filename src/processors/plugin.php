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

		$this->getSubProGeoip()->run();

		switch ( Services::Request()->query( 'shield_action', '' ) ) {
			case 'dump_tracking_data':
				add_action( 'wp_loaded', [ $this, 'dumpTrackingData' ] );
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

		add_action( 'admin_footer', [ $this, 'printAdminFooterItems' ], 100, 0 );
	}

	public function onWpLoaded() {
		if ( $this->getCon()->isValidAdminArea() ) {
			$this->maintainPluginLoadPosition();
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Badge
	 */
	protected function getSubProBadge() {
		return $this->getSubPro( 'badge' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Geoip
	 */
	public function getSubProGeoip() {
		return $this->getSubPro( 'geoip' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_CronDaily
	 */
	protected function getSubProCronDaily() {
		return $this->getSubPro( 'crondaily' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_CronHourly
	 */
	protected function getSubProCronHourly() {
		return $this->getSubPro( 'cronhourly' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Tracking
	 */
	protected function getSubProTracking() {
		return $this->getSubPro( 'tracking' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_ImportExport
	 */
	public function getSubProImportExport() {
		return $this->getSubPro( 'importexport' );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin_Notes
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
			'geoip'        => 'ICWP_WPSF_Processor_Plugin_Geoip',
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
			$aRenderData = [
				'strings'     => [
					'title' => $this->getCon()->getHumanName(),
				],
				'js_snippets' => []
			];
			echo $this->getMod()
					  ->renderTemplate( 'snippets/toaster.twig', $aRenderData, true );
		}
	}

	private function printPluginDeactivateSurvey() {
		if ( Services::WpPost()->isCurrentPage( 'plugins.php' ) ) {

			$aOpts = [
				'reason_confusing'   => "It's too confusing",
				'reason_expected'    => "It's not what I expected",
				'reason_accident'    => "I downloaded it accidentally",
				'reason_alternative' => "I'm already using an alternative",
				'reason_trust'       => "I don't trust the developer :(",
				'reason_not_work'    => "It doesn't work",
				'reason_errors'      => "I'm getting errors",
			];

			$aRenderData = [
				'strings'     => [
					'editing_restricted' => _wpsf__( 'Editing this option is currently restricted.' ),
				],
				'inputs'      => [
					'checkboxes' => $this->loadDP()->shuffleArray( $aOpts )
				],
				'js_snippets' => []
			];
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
		$oWpPlugins = Services::WpPlugins();
		$sBaseFile = $this->getCon()->getPluginBaseFile();
		$nLoadPosition = $oWpPlugins->getActivePluginLoadPosition( $sBaseFile );
		if ( $nLoadPosition !== 0 && $nLoadPosition > 0 ) {
			$oWpPlugins->setActivePluginLoadFirst( $sBaseFile );
		}
	}

	/**
	 * @param array $aNoticeAttributes
	 * @see autoAddToAdminNotices()
	 */
	protected function addNotice_override_forceoff( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		$oCon = $this->getCon();
		if ( $oCon->getIfForceOffActive() ) {
			$aRenderData = [
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => [
					'title'   => sprintf( '%s: %s', _wpsf__( 'Warning' ), sprintf( _wpsf__( '%s is not protecting your site' ), $oCon->getHumanName() ) ),
					'message' => sprintf(
						_wpsf__( 'Please delete the "%s" file to reactivate %s protection' ),
						'forceOff',
						$oCon->getHumanName()
					),
					'delete'  => _wpsf__( 'Click here to automatically delete the file' )
				],
				'ajax'              => [
					'delete_forceoff' => $oFO->getAjaxActionData( 'delete_forceoff', true )
				]
			];
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @param array $aNoticeAttributes
	 * @see autoAddToAdminNotices()
	 */
	protected function addNotice_plugin_mailing_list_signup( $aNoticeAttributes ) {
		$oModCon = $this->getMod();
		$sName = $this->getCon()->getHumanName();
		$nDays = $this->getInstallationDays();
		if ( $this->getIfShowAdminNotices() && $nDays >= 5 ) {
			$oUser = Services::WpUsers()->getCurrentWpUser();
			$aRenderData = [
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => [
					'title'          => 'Come and Join Us!',
					'yes'            => "Yes please! I'd love to join in and learn more",
					'no'             => "No thanks, I'm not interested in such groups",
					'your_name'      => _wpsf__( 'Your Name' ),
					'your_email'     => _wpsf__( 'Your Email' ),
					'dismiss'        => "No thanks, I'm not interested in such informative groups",
					'summary'        => sprintf( 'The %s security team is running an initiative to raise awareness of WordPress Security
				and to provide further help with the %s security plugin. Get Involved here:', $sName, $sName ),
					'privacy_policy' => sprintf(
						'I certify that I have read and agree to the <a href="%s" target="_blank">Privacy Policy</a>',
						$this->getMod()->getDef( 'href_privacy_policy' )
					),
				],
				'hrefs'             => [
					'privacy_policy' => $oModCon->getDef( 'href_privacy_policy' )
				],
				'install_days'      => $nDays,
				'vars'              => [
					'name'       => $oUser->first_name,
					'user_email' => $oUser->user_email
				]
			];
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * Lets you remove certain plugin conflicts that might interfere with this plugin
	 */
	protected function removePluginConflicts() {
		if ( class_exists( 'AIO_WP_Security' ) && isset( $GLOBALS[ 'aio_wp_security' ] ) ) {
			remove_action( 'init', [ $GLOBALS[ 'aio_wp_security' ], 'wp_security_plugin_init' ], 0 );
		}
	}
}