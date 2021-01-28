<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\WhiteLabel;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
use FernleafSystems\Wordpress\Services\Services;

class ApplyLabels {

	use ModConsumer;
	use OneTimeExecute;

	protected function canRun() {
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledWhitelabel();
	}

	protected function run() {
		$con = $this->getCon();
		add_action( 'init', [ $this, 'onWpInit' ] );
		add_filter( $con->prefix( 'is_relabelled' ), '__return_true' );
		add_filter( $con->prefix( 'plugin_labels' ), [ $this, 'applyPluginLabels' ] );
		add_filter( 'plugin_row_meta', [ $this, 'removePluginMetaLinks' ], 200, 2 );
		add_action( 'admin_print_footer_scripts-plugin-editor.php', [ $this, 'hideFromPluginEditor' ] );
	}

	public function onWpInit() {
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isWlHideUpdates() && $this->isNeedToHideUpdates() && !$this->getCon()->isPluginAdmin() ) {
			$this->hideUpdates();
		}
	}

	/**
	 * Depending on the page, we hide the update data,
	 * or we adjust the number of displayed updates counts
	 */
	protected function hideUpdates() {
		if ( in_array( Services::WpPost()->getCurrentPage(), [ 'plugins.php', 'update-core.php' ] ) ) {
			add_filter( 'site_transient_update_plugins', [ $this, 'hidePluginUpdatesFromUI' ] );
		}
		else {
			add_filter( 'wp_get_update_data', [ $this, 'adjustUpdateDataCount' ] );
		}
	}

	/**
	 * Adjusts the available updates count so as not to include Shield updates if they're hidden
	 * @param array $aUpdateData
	 * @return array
	 */
	public function adjustUpdateDataCount( $aUpdateData ) {

		$file = $this->getCon()->base_file;
		if ( Services::WpPlugins()->isUpdateAvailable( $file ) ) {
			$aUpdateData[ 'counts' ][ 'total' ]--;
			$aUpdateData[ 'counts' ][ 'plugins' ]--;
		}

		return $aUpdateData;
	}

	public function hideFromPluginEditor() {
		$con = $this->getCon();
		$js = Services::Data()->readFileContentsUsingInclude( $con->getPath_AssetJs( 'whitelabel.js' ) );
		echo sprintf( '<script type="text/javascript">%s</script>', sprintf( $js, $con->base_file ) );
	}

	/**
	 * @param array $pluginLabels
	 * @return array
	 */
	public function applyPluginLabels( $pluginLabels ) {
		/** @var SecurityAdmin\ModCon $mod */
		$mod = $this->getMod();

		$labels = $mod->getWhitelabelOptions();

		// these are the old white labelling keys which will be replaced upon final release of white labelling.
		$sServiceName = $labels[ 'name_main' ];
		$pluginLabels[ 'Name' ] = $sServiceName;
		$pluginLabels[ 'Title' ] = $sServiceName;
		$pluginLabels[ 'Author' ] = $labels[ 'name_company' ];
		$pluginLabels[ 'AuthorName' ] = $labels[ 'name_company' ];
		$pluginLabels[ 'MenuTitle' ] = $labels[ 'name_menu' ];

		if ( !empty( $labels[ 'description' ] ) ) {
			$pluginLabels[ 'Description' ] = $labels[ 'description' ];
		}

		if ( !empty( $labels[ 'url_home' ] ) ) {
			$pluginLabels[ 'PluginURI' ] = $labels[ 'url_home' ];
			$pluginLabels[ 'AuthorURI' ] = $labels[ 'url_home' ];
		}

		if ( !empty( $labels[ 'url_icon' ] ) ) {
			$pluginLabels[ 'icon_url_16x16' ] = $labels[ 'url_icon' ];
			$pluginLabels[ 'icon_url_32x32' ] = $labels[ 'url_icon' ];
		}

		if ( !empty( $labels[ 'url_dashboardlogourl' ] ) ) {
			$pluginLabels[ 'icon_url_128x128' ] = $labels[ 'url_dashboardlogourl' ];
		}

		return array_merge( $labels, $pluginLabels );
	}

	/**
	 * @filter
	 * @param array  $aPluginMeta
	 * @param string $pluginBaseFile
	 * @return array
	 */
	public function removePluginMetaLinks( $aPluginMeta, $pluginBaseFile ) {
		if ( $pluginBaseFile == $this->getCon()->base_file ) {
			unset( $aPluginMeta[ 2 ] ); // View details
			unset( $aPluginMeta[ 3 ] ); // Rate 5*
		}
		return $aPluginMeta;
	}

	/**
	 * Hides the update if the page loaded is the plugins page or the updates page.
	 * @param \stdClass $plugins
	 * @return \stdClass
	 */
	public function hidePluginUpdatesFromUI( $plugins ) {
		$file = $this->getCon()->base_file;
		if ( isset( $plugins->response[ $file ] ) ) {
			unset( $plugins->response[ $file ] );
		}
		return $plugins;
	}

	private function isNeedToHideUpdates() :bool {
		return is_admin() && !Services::WpGeneral()->isCron();
	}
}