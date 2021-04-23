<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\WhiteLabel;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
use FernleafSystems\Wordpress\Services\Services;

class WhitelabelController extends ExecOnceModConsumer {

	public function isEnabled() :bool {
		/** @var SecurityAdmin\ModCon $mod */
		$mod = $this->getMod();
		return $this->getCon()->isPremiumActive()
			   && $this->getOptions()->isOpt( 'whitelabel_enable', 'Y' )
			   && $mod->getSecurityAdminController()->isEnabledSecAdmin();
	}

	protected function canRun() :bool {
		return $this->isEnabled();
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
		if ( $opts->isOpt( 'wl_hide_updates', 'Y' ) && $this->isNeedToHideUpdates() && !$this->getCon()
																							 ->isPluginAdmin() ) {
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
		// TODO
	}

	/**
	 * @param array $pluginLabels
	 * @return array
	 */
	public function applyPluginLabels( array $pluginLabels ) :array {
		$labels = ( new BuildOptions() )
			->setMod( $this->getMod() )
			->build();

		// these are the old white labelling keys which will be replaced upon final release of white labelling.
		$serviceName = $labels[ 'name_main' ];
		if ( !empty( $serviceName ) ) {
			$pluginLabels[ 'Name' ] = $serviceName;
			$pluginLabels[ 'Title' ] = $serviceName;
		}
		$companyName = $labels[ 'name_company' ];
		if ( !empty( $companyName ) ) {
			$pluginLabels[ 'Author' ] = $labels[ 'name_company' ];
			$pluginLabels[ 'AuthorName' ] = $labels[ 'name_company' ];
		}
		$menuName = empty( $labels[ 'name_menu' ] ) ? $serviceName : $labels[ 'name_menu' ];
		if ( !empty( $menuName ) ) {
			$pluginLabels[ 'MenuTitle' ] = $menuName;
		}

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

	public function isReplacePluginBadge() :bool {
		return $this->getOptions()->isOpt( 'wl_replace_badge_url', 'Y' );
	}

	public function verifyUrls() {
		$DP = Services::Data();
		$opts = $this->getOptions();
		$optsBuilder = ( new BuildOptions() )->setMod( $this->getMod() );
		foreach ( [ 'wl_menuiconurl', 'wl_dashboardlogourl', 'wl_login2fa_logourl' ] as $key ) {
			if ( $opts->isOptChanged( $key ) && !$DP->isValidWebUrl( $optsBuilder->buildWlImageUrl( $key ) ) ) {
				$opts->resetOptToDefault( $key );
			}
		}
	}

	/**
	 * @filter
	 * @param array  $pluginMeta
	 * @param string $pluginBaseFile
	 * @return array
	 */
	public function removePluginMetaLinks( $pluginMeta, $pluginBaseFile ) {
		if ( $pluginBaseFile == $this->getCon()->base_file ) {
			unset( $pluginMeta[ 2 ] ); // View details
			unset( $pluginMeta[ 3 ] ); // Rate 5*
		}
		return $pluginMeta;
	}

	/**
	 * Hides the update if the page loaded is the plugins page or the updates page.
	 * @param \stdClass $plugins
	 * @return \stdClass
	 */
	public function hidePluginUpdatesFromUI( $plugins ) {
		unset( $plugins->response[ $this->getCon()->base_file ] );
		return $plugins;
	}

	private function isNeedToHideUpdates() :bool {
		return is_admin() && !Services::WpGeneral()->isCron();
	}
}