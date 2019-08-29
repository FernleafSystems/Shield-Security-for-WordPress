<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components\PluginBadge;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin extends ICWP_WPSF_Processor_BasePlugin {

	/**
	 */
	public function run() {
		parent::run();
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();

		$this->getSubProCronDaily()->run();
		$this->getSubProCronHourly()->run();

		$this->removePluginConflicts();

		( new PluginBadge() )
			->setMod( $oMod )
			->run();

		if ( $oMod->isTrackingEnabled() || !$oMod->isTrackingPermissionSet() ) {
			$this->getSubProTracking()->run();
		}

		if ( $oMod->isImportExportPermitted() ) {
			$this->getSubProImportExport()->run();
		}

		switch ( $this->getCon()->getShieldAction() ) {
			case 'dump_tracking_data':
				add_action( 'wp_loaded', [ $this, 'dumpTrackingData' ] );
				break;

			case 'importexport_export':
			case 'importexport_import':
			case 'importexport_handshake':
			case 'importexport_updatenotified':
				if ( $oMod->isImportExportPermitted() ) {
					add_action( 'init', [ $this->getSubProImportExport(), 'runAction' ] );
				}
				break;
			default:
				break;
		}

		add_action( 'admin_footer', [ $this, 'printAdminFooterItems' ], 100, 0 );
	}

	public function onWpLoaded() {
		$oCon = $this->getCon();
		if ( $oCon->isValidAdminArea() ) {
			$this->maintainPluginLoadPosition();
		}
		add_filter( $oCon->prefix( 'dashboard_widget_content' ), [ $this, 'gatherPluginWidgetContent' ], 100 );
	}

	/**
	 * @param array $aContent
	 * @return array
	 */
	public function gatherPluginWidgetContent( $aContent ) {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$oCon = $this->getCon();

		$aLabels = $oCon->getLabels();
		$sFooter = sprintf( __( '%s is provided by %s', 'wp-simple-firewall' ), $oCon->getHumanName(),
			sprintf( '<a href="%s">%s</a>', $aLabels[ 'AuthorURI' ], $aLabels[ 'Author' ] )
		);

		$aDisplayData = [
			'sInstallationDays' => sprintf( __( 'Days Installed: %s', 'wp-simple-firewall' ), $this->getInstallationDays() ),
			'sFooter'           => $sFooter,
			'sIpAddress'        => sprintf( __( 'Your IP address is: %s', 'wp-simple-firewall' ), Services::IP()
																										  ->getRequestIp() )
		];

		if ( !is_array( $aContent ) ) {
			$aContent = [];
		}
		$aContent[] = $oMod->renderTemplate( 'snippets/widget_dashboard_plugin.php', $aDisplayData );
		return $aContent;
	}

	/**
	 * @return \ICWP_WPSF_Processor_Plugin_CronDaily
	 */
	protected function getSubProCronDaily() {
		return $this->getSubPro( 'crondaily' );
	}

	/**
	 * @return \ICWP_WPSF_Processor_Plugin_CronHourly
	 */
	protected function getSubProCronHourly() {
		return $this->getSubPro( 'cronhourly' );
	}

	/**
	 * @return \ICWP_WPSF_Processor_Plugin_Tracking
	 */
	protected function getSubProTracking() {
		return $this->getSubPro( 'tracking' );
	}

	/**
	 * @return \ICWP_WPSF_Processor_Plugin_ImportExport
	 */
	public function getSubProImportExport() {
		return $this->getSubPro( 'importexport' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'importexport' => 'ICWP_WPSF_Processor_Plugin_ImportExport',
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

			$aData = [
				'strings'     => [
					'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
				],
				'inputs'      => [
					'checkboxes' => Services::DataManipulation()->shuffleArray( $aOpts )
				],
				'js_snippets' => []
			];
			echo $this->getMod()
					  ->renderTemplate( 'snippets/plugin-deactivate-survey.php', $aData );
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
		$this->getCon()->fireEvent( 'test_cron_run' );
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
	 * Lets you remove certain plugin conflicts that might interfere with this plugin
	 */
	protected function removePluginConflicts() {
		if ( class_exists( 'AIO_WP_Security' ) && isset( $GLOBALS[ 'aio_wp_security' ] ) ) {
			remove_action( 'init', [ $GLOBALS[ 'aio_wp_security' ], 'wp_security_plugin_init' ], 0 );
		}
	}
}