<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;
use FernleafSystems\Wordpress\Services\Services;

class WpvAddPluginRows {

	use Controller\ScanControllerConsumer;
	use ExecOnce;

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

	protected function canRun() :bool {
		return $this->isWpvulnPluginsHighlightEnabled() && $this->countVulnerablePlugins() > 0;
	}

	private function isWpvulnPluginsHighlightEnabled() :bool {
		$scanCon = $this->getScanController();
		if ( $scanCon->isEnabled() ) {
			$opt = apply_filters( 'shield/wpvuln_scan_display', 'securityadmin' );
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
			$oVulnerableRes = $this->getScanController()->getResultsForDisplay();
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
		/** @var Controller\Wpv $scanCon */
		$scanCon = $this->getScanController();

		if ( $scanCon->hasVulnerabilities( $pluginFile ) ) {
			$name = $scanCon->getCon()->getHumanName();
			$plugin = Services::WpPlugins()->getPluginAsVo( $pluginFile );
			echo $scanCon->getMod()->renderTemplate( '/snippets/plugin_vulnerability.twig', [
				'strings' => [
					'known_vuln' => sprintf(
						__( '%s has discovered that the currently installed version of the %s plugin has known security vulnerabilities.', 'wp-simple-firewall' ),
						$name, '<strong>'.$pData[ 'Name' ].'</strong>' ),
					'more_info'  => __( 'More Info', 'wp-simple-firewall' ),
				],
				'hrefs'   => [
					'vuln_lookup' => add_query_arg(
						[
							'type'    => $plugin->asset_type,
							'slug'    => $plugin->slug,
							'version' => $plugin->Version,
						],
						'https://shsec.io/shieldvulnerabilitylookup'
					)
				],
				'vars'    => [
					'colspan' => $this->nColumnsCount
				],
			] );
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
									->getScansController()
									->getScanResultsCount()
									->countVulnerableAssets();
		}
		return $this->vulnCount;
	}
}