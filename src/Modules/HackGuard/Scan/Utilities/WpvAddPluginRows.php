<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\PluginVulnerabilityWarning;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;
use FernleafSystems\Wordpress\Services\Services;

class WpvAddPluginRows {

	use PluginControllerConsumer;
	use ExecOnce;

	/**
	 * @var int
	 */
	private $colsCount;

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
		if ( self::con()->comps->scans->WPV()->isEnabled() ) {
			$opt = apply_filters( 'shield/wpvuln_scan_display', 'securityadmin' );
		}
		else {
			$opt = 'disabled';
		}
		return ( $opt != 'disabled' ) && Services::WpUsers()->isUserAdmin()
			   && ( ( $opt != 'securityadmin' ) || self::con()->isPluginAdmin() );
	}

	private function addPluginVulnerabilityRows() {
		// These 3 add the 'Vulnerable' plugin status view.
		// BUG: when vulnerable is active, only 1 plugin is available to "All" status. don't know fix.
		add_action( 'pre_current_active_plugins', [ $this, 'addVulnerablePluginStatusView' ], 1000 );
		add_filter( 'all_plugins', [ $this, 'filterPluginsToView' ], 1000 );
		add_filter( 'views_plugins', [ $this, 'addPluginsStatusViewLink' ], 1000 );
		add_filter( 'manage_plugins_columns', [ $this, 'fCountColumns' ], 1000 );

		foreach ( Services::WpPlugins()->getInstalledPluginFiles() as $file ) {
			add_action( "after_plugin_row_$file", function ( $pluginFile ) {
				echo self::con()->action_router->render( PluginVulnerabilityWarning::SLUG, [
					'plugin_file'   => $pluginFile,
					'columns_count' => $this->colsCount
				] );
			}, 100 );
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
			/** @var Wpv\ResultsSet $vulnerableRes */
			$vulnerableRes = self::con()->comps->scans->WPV()->getResultsForDisplay();
			global $status;
			$status = 'vulnerable';
			$plugins = \array_intersect_key(
				$plugins,
				\array_flip( $vulnerableRes->getUniqueSlugs() )
			);
		}
		return $plugins;
	}

	/**
	 * @param array $cols
	 * @return array
	 */
	public function fCountColumns( $cols ) {
		if ( !isset( $this->colsCount ) ) {
			$this->colsCount = \count( $cols );
		}
		return $cols;
	}

	private function countVulnerablePlugins() :int {
		return self::con()->comps->scans->getScanResultsCount()->countVulnerableAssets();
	}
}