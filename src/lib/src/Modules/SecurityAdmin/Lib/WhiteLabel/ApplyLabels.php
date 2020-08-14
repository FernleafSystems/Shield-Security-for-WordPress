<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\WhiteLabel;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
use FernleafSystems\Wordpress\Services\Services;

class ApplyLabels {

	use ModConsumer;

	public function run() {
		$oCon = $this->getCon();
		add_action( 'init', [ $this, 'onWpInit' ] );
		add_filter( $oCon->prefix( 'is_relabelled' ), '__return_true' );
		add_filter( $oCon->prefix( 'plugin_labels' ), [ $this, 'applyPluginLabels' ] );
		add_filter( 'plugin_row_meta', [ $this, 'removePluginMetaLinks' ], 200, 2 );
		add_action( 'admin_print_footer_scripts-plugin-editor.php', [ $this, 'hideFromPluginEditor' ] );
	}

	public function onWpInit() {
		/** @var SecurityAdmin\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isWlHideUpdates() && $this->isNeedToHideUpdates() && !$this->getCon()->isPluginAdmin() ) {
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

		$sFile = $this->getCon()->getPluginBaseFile();
		if ( Services::WpPlugins()->isUpdateAvailable( $sFile ) ) {
			$aUpdateData[ 'counts' ][ 'total' ]--;
			$aUpdateData[ 'counts' ][ 'plugins' ]--;
		}

		return $aUpdateData;
	}

	public function hideFromPluginEditor() {
		$oCon = $this->getCon();
		$sJs = Services::Data()->readFileContentsUsingInclude( $oCon->getPath_AssetJs( 'whitelabel.js' ) );
		echo sprintf( '<script type="text/javascript">%s</script>', sprintf( $sJs, $oCon->getPluginBaseFile() ) );
	}

	/**
	 * @param array $aPluginLabels
	 * @return array
	 */
	public function applyPluginLabels( $aPluginLabels ) {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oMod */
		$oMod = $this->getMod();

		$aWhiteLabels = $oMod->getWhitelabelOptions();

		// these are the old white labelling keys which will be replaced upon final release of white labelling.
		$sServiceName = $aWhiteLabels[ 'name_main' ];
		$aPluginLabels[ 'Name' ] = $sServiceName;
		$aPluginLabels[ 'Title' ] = $sServiceName;
		$aPluginLabels[ 'Author' ] = $aWhiteLabels[ 'name_company' ];
		$aPluginLabels[ 'AuthorName' ] = $aWhiteLabels[ 'name_company' ];
		$aPluginLabels[ 'MenuTitle' ] = $aWhiteLabels[ 'name_menu' ];

		$sTagLine = $aWhiteLabels[ 'description' ];
		if ( !empty( $sTagLine ) ) {
			$aPluginLabels[ 'Description' ] = $sTagLine;
		}

		$sUrl = $aWhiteLabels[ 'url_home' ];
		if ( !empty( $sUrl ) ) {
			$aPluginLabels[ 'PluginURI' ] = $sUrl;
			$aPluginLabels[ 'AuthorURI' ] = $sUrl;
		}

		$sIconUrl = $aWhiteLabels[ 'url_icon' ];
		if ( !empty( $sIconUrl ) ) {
			$aPluginLabels[ 'icon_url_16x16' ] = $sIconUrl;
			$aPluginLabels[ 'icon_url_32x32' ] = $sIconUrl;
		}

		$sLogoUrl = $aWhiteLabels[ 'url_dashboardlogourl' ];
		if ( !empty( $sLogoUrl ) ) {
			$aPluginLabels[ 'icon_url_128x128' ] = $sLogoUrl;
		}

		return array_merge( $aWhiteLabels, $aPluginLabels );
	}

	/**
	 * @filter
	 * @param array  $aPluginMeta
	 * @param string $sPluginBaseFileName
	 * @return array
	 */
	public function removePluginMetaLinks( $aPluginMeta, $sPluginBaseFileName ) {
		if ( $sPluginBaseFileName == $this->getCon()->getPluginBaseFile() ) {
			unset( $aPluginMeta[ 2 ] ); // View details
			unset( $aPluginMeta[ 3 ] ); // Rate 5*
		}
		return $aPluginMeta;
	}

	/**
	 * Hides the update if the page loaded is the plugins page or the updates page.
	 * @param \stdClass $oPlugins
	 * @return \stdClass
	 */
	public function hidePluginUpdatesFromUI( $oPlugins ) {
		$sFile = $this->getCon()->getPluginBaseFile();
		if ( isset( $oPlugins->response[ $sFile ] ) ) {
			unset( $oPlugins->response[ $sFile ] );
		}
		return $oPlugins;
	}

	/**
	 * @return bool
	 */
	private function isNeedToHideUpdates() {
		return is_admin() && !Services::WpGeneral()->isCron();
	}
}