<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Options\CleanStorage;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$this->removePluginConflicts();
		( new Lib\OverrideLocale() )
			->setMod( $this->getMod() )
			->run();

		$mod->getPluginBadgeCon()->run();

		if ( $opts->isTrackingEnabled() || !$opts->isTrackingPermissionSet() ) {
			( new \ICWP_WPSF_Processor_Plugin_Tracking( $mod ) )->execute();
		}

		if ( $opts->isImportExportPermitted() ) {
			$mod->getImpExpController()->run();
		}

		$con = $this->getCon();
		switch ( $con->getShieldAction() ) {
			case 'dump_tracking_data':
				add_action( 'wp_loaded', [ $this, 'dumpTrackingData' ] );
				break;
			default:
				break;
		}

		add_action( 'admin_footer', [ $this, 'printAdminFooterItems' ], 100, 0 );

		add_filter( $con->prefix( 'delete_on_deactivate' ), function ( $bDelete ) use ( $opts ) {
			return $bDelete || $opts->isOpt( 'delete_on_deactivate', 'Y' );
		} );

		add_action( $con->prefix( 'dashboard_widget_content' ),
			[ $this, 'printDashboardWidget' ],
			100
		);
	}

	public function printDashboardWidget() {
		$oCon = $this->getCon();
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		$aLabels = $oCon->getLabels();

		echo $this->getMod()->renderTemplate(
			'snippets/widget_dashboard_plugin.php',
			[
				'install_days' => sprintf( __( 'Days Installed: %s', 'wp-simple-firewall' ), $oOpts->getInstallationDays() ),
				'footer'       => sprintf( __( '%s is provided by %s', 'wp-simple-firewall' ), $oCon->getHumanName(),
					sprintf( '<a href="%s" target="_blank">%s</a>', $aLabels[ 'AuthorURI' ], $aLabels[ 'Author' ] )
				),
				'ip_address'   => sprintf( __( 'Your IP address is: %s', 'wp-simple-firewall' ),
					Services::IP()->getRequestIp() )
			]
		);
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
			echo $this->getMod()->renderTemplate(
				'snippets/toaster.twig', $aRenderData, true
			);
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
			echo $this->getMod()->renderTemplate(
				'snippets/plugin-deactivate-survey.php', $aData
			);
		}
	}

	public function dumpTrackingData() {
		if ( $this->getCon()->isPluginAdmin() ) {
			echo sprintf( '<pre><code>%s</code></pre>',
				print_r( ( new \ICWP_WPSF_Processor_Plugin_Tracking( $this->getMod() ) )->collectTrackingData(), true ) );
			die();
		}
	}

	public function runDailyCron() {
		$this->getCon()->fireEvent( 'test_cron_run' );

		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isImportExportPermitted() ) {
			try {
				( new Lib\ImportExport\Import() )
					->setMod( $this->getMod() )
					->fromSite( $oOpts->getImportExportMasterImportUrl() );
			}
			catch ( \Exception $e ) {
			}
		}

		( new CleanStorage() )
			->setCon( $this->getCon() )
			->run();
	}

	/**
	 * Lets you remove certain plugin conflicts that might interfere with this plugin
	 */
	protected function removePluginConflicts() {
		if ( class_exists( 'AIO_WP_Security' ) && isset( $GLOBALS[ 'aio_wp_security' ] ) ) {
			remove_action( 'init', [ $GLOBALS[ 'aio_wp_security' ], 'wp_security_plugin_init' ], 0 );
		}
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param array $data
	 * @return array
	 */
	public function tracking_DataCollect( $data ) {
		$data = parent::tracking_DataCollect( $data );
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$slug = $mod->getSlug();
		if ( empty( $data[ $slug ][ 'options' ][ 'unique_installation_id' ] ) ) {
			$data[ $slug ][ 'options' ][ 'unique_installation_id' ] = $mod->getPluginInstallationId();
			$data[ $slug ][ 'options' ][ 'new_unique_installation_id' ] = $this->getCon()->getSiteInstallationId();
		}
		return $data;
	}
}