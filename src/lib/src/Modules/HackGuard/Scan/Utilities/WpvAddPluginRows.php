<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;
use FernleafSystems\Wordpress\Services\Services;

class WpvAddPluginRows {

	use Controller\ScanControllerConsumer;
	use OneTimeExecute;

	/**
	 * @var int
	 */
	private $nColumnsCount;

	/**
	 * @var int
	 */
	private $vulnCount;

	protected function run() {
		$this->addPluginVulnerabilityRows();
	}

	protected function canRun() {
		return $this->isWpvulnPluginsHighlightEnabled() && $this->countVulnerablePlugins() > 0;
	}

	private function isWpvulnPluginsHighlightEnabled() :bool {
		$scanCon = $this->getScanController();
		if ( $scanCon->isEnabled() ) {
			$opt = apply_filters( 'icwp_shield_wpvuln_scan_display', 'securityadmin' );
		}
		else {
			$opt = 'disabled';
		}
		return ( $opt != 'disabled' ) && Services::WpUsers()->isUserAdmin()
			   && ( ( $opt != 'securityadmin' ) || $scanCon->getCon()->isPluginAdmin() );
	}

	private function addPluginVulnerabilityRows() {
		// These 3 add the 'Vulnerable' plugin status view.
		// BUG: when vulnerable is active, only 1 plugin is available to "All" status. don't know fix.
		add_action( 'pre_current_active_plugins', [ $this, 'addVulnerablePluginStatusView' ], 1000 );
		add_filter( 'all_plugins', [ $this, 'filterPluginsToView' ], 1000 );
		add_filter( 'views_plugins', [ $this, 'addPluginsStatusViewLink' ], 1000 );
		add_filter( 'manage_plugins_columns', [ $this, 'fCountColumns' ], 1000 );
		foreach ( Services::WpPlugins()->getInstalledPluginFiles() as $file ) {
			add_action( "after_plugin_row_$file", [ $this, 'attachVulnerabilityWarning' ], 100, 2 );
		}
	}

	public function addVulnerablePluginStatusView() {
		if ( Services::Request()->query( 'plugin_status' ) == 'vulnerable' ) {
			global $status;
			$status = 'vulnerable';
		}
		add_filter( 'views_plugins', [ $this, 'addPluginsStatusViewLink' ], 1000 );
	}

	/**
	 * FILTER
	 * @param array $views
	 * @return array
	 */
	public function addPluginsStatusViewLink( $views ) {
		global $status;

		$views[ 'vulnerable' ] = sprintf( "<a href='%s' %s>%s</a>",
			add_query_arg( 'plugin_status', 'vulnerable', 'plugins.php' ),
			( 'vulnerable' === $status ) ? ' class="current"' : '',
			sprintf( '%s <span class="count">(%s)</span>',
				__( 'Vulnerable', 'wp-simple-firewall' ),
				number_format_i18n( $this->countVulnerablePlugins() )
			)
		);
		return $views;
	}

	/**
	 * FILTER
	 * @param array $plugins
	 * @return array
	 */
	public function filterPluginsToView( $plugins ) {
		if ( Services::Request()->query( 'plugin_status' ) == 'vulnerable' ) {
			/** @var Wpv\ResultsSet $oVulnerableRes */
			$oVulnerableRes = $this->getScanController()->getAllResults();
			global $status;
			$status = 'vulnerable';
			$plugins = array_intersect_key(
				$plugins,
				array_flip( $oVulnerableRes->getUniqueSlugs() )
			);
		}
		return $plugins;
	}

	/**
	 * @param string $pluginFile
	 * @param array  $pData
	 */
	public function attachVulnerabilityWarning( $pluginFile, $pData ) {
		$scanCon = $this->getScanController();

		$vulns = $scanCon->getPluginVulnerabilities( $pluginFile );
		if ( count( $vulns ) > 0 ) {
			$sOurName = $scanCon->getCon()->getHumanName();
			echo $scanCon->getMod()
						 ->renderTemplate(
							 'snippets/plugin-vulnerability.php',
							 [
								 'strings'  => [
									 'known_vuln'     => sprintf( __( '%s has discovered that the currently installed version of the %s plugin has known security vulnerabilities.', 'wp-simple-firewall' ),
										 $sOurName, '<strong>'.$pData[ 'Name' ].'</strong>' ),
									 'name'           => __( 'Vulnerability Name', 'wp-simple-firewall' ),
									 'type'           => __( 'Vulnerability Type', 'wp-simple-firewall' ),
									 'fixed_versions' => __( 'Fixed Versions', 'wp-simple-firewall' ),
									 'more_info'      => __( 'More Info', 'wp-simple-firewall' ),
								 ],
								 'vulns'    => $vulns,
								 'nColspan' => $this->nColumnsCount
							 ]
						 );
		}
	}

	/**
	 * @param array $cols
	 * @return array
	 */
	public function fCountColumns( $cols ) {
		if ( !isset( $this->nColumnsCount ) ) {
			$this->nColumnsCount = count( $cols );
		}
		return $cols;
	}

	private function countVulnerablePlugins() :int {
		if ( !isset( $this->vulnCount ) ) {
			$this->vulnCount = $this->getScanController()
									->getAllResults()
									->countUniqueSlugsForPluginsContext();
		}
		return $this->vulnCount;
	}
}