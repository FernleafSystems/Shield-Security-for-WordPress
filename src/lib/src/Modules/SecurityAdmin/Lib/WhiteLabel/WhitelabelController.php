<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\WhiteLabel;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Labels;
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
		add_filter( $con->prefix( 'is_relabelled' ), '__return_true' );
		add_filter( $con->prefix( 'labels' ), [ $this, 'applyWhiteLabels' ], 200 );
		add_filter( 'plugin_row_meta', [ $this, 'removePluginMetaLinks' ], 200, 2 );

		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isOpt( 'wl_hide_updates', 'Y' ) && is_admin()
			 && !Services::WpGeneral()->isCron()
			 && !$this->getCon()->isPluginAdmin() ) {

			if ( in_array( Services::WpPost()->getCurrentPage(), [ 'plugins.php', 'update-core.php' ] ) ) {
				add_filter( 'site_transient_update_plugins', [ $this, 'hidePluginUpdatesFromUI' ] );
			}
			else {
				add_filter( 'wp_get_update_data', [ $this, 'adjustUpdateDataCount' ] );
			}
		}
	}

	public function applyWhiteLabels( Labels $labels ) :Labels {
		$opts = $this->getOptions();

		// these are the old white labelling keys which will be replaced upon final release of white labelling.
		$name = $opts->getOpt( 'wl_pluginnamemain' );
		if ( !empty( $name ) ) {
			$labels->Name = $name;
			$labels->Title = $name;
		}

		$companyName = $opts->getOpt( 'wl_companyname' );
		if ( !empty( $companyName ) ) {
			$labels->Author = $companyName;
			$labels->AuthorName = $companyName;
		}

		$labels->MenuTitle = empty( $opts->getOpt( 'wl_namemenu' ) ) ? $labels->Name : $opts->getOpt( 'wl_namemenu' );

		if ( !empty( $opts->getOpt( 'wl_description' ) ) ) {
			$labels->Description = $opts->getOpt( 'wl_description' );
		}

		$homeURL = $opts->getOpt( 'wl_homeurl' );
		if ( !empty( $homeURL ) ) {
			$labels->PluginURI = $homeURL;
			$labels->AuthorURI = $homeURL;
			$labels->url_helpdesk = $homeURL;
		}

		$urlIcon = $this->constructImageURL( 'wl_menuiconurl' );
		if ( !empty( $urlIcon ) ) {
			$labels->icon_url_16x16 = $urlIcon;
			$labels->icon_url_32x32 = $urlIcon;
		}

		$urlDashboardLogo = $this->constructImageURL( 'wl_dashboardlogourl' );
		if ( !empty( $urlDashboardLogo ) ) {
			$labels->icon_url_128x128 = $urlDashboardLogo;
		}

		$urlPageBanner = $this->constructImageURL( 'wl_login2fa_logourl' );
		if ( !empty( $urlPageBanner ) ) {
			$labels->url_img_pagebanner = $urlPageBanner;
		}

		$labels->url_secadmin_forgotten_key = $labels->AuthorURI;

		return $labels;
	}

	/**
	 * Adjusts the available updates count so as not to include Shield updates if they're hidden
	 * @param array $updateData
	 * @return array
	 */
	public function adjustUpdateDataCount( $updateData ) {

		$file = $this->getCon()->base_file;
		if ( Services::WpPlugins()->isUpdateAvailable( $file ) ) {
			$updateData[ 'counts' ][ 'total' ]--;
			$updateData[ 'counts' ][ 'plugins' ]--;
		}

		return $updateData;
	}

	public function isReplacePluginBadge() :bool {
		return $this->getOptions()->isOpt( 'wl_replace_badge_url', 'Y' );
	}

	public function verifyUrls() {
		$DP = Services::Data();
		$opts = $this->getOptions();
		foreach ( [ 'wl_menuiconurl', 'wl_dashboardlogourl', 'wl_login2fa_logourl' ] as $key ) {
			if ( $opts->isOptChanged( $key ) && !$DP->isValidWebUrl( $this->constructImageURL( $key ) ) ) {
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

	/**
	 * We cater for 3 options:
	 * Full URL
	 * Relative path URL: i.e. starts with /
	 * Or Plugin image URL i.e. doesn't start with HTTP or /
	 */
	private function constructImageURL( string $key ) :string {
		$opts = $this->getOptions();

		$url = $opts->getOpt( $key );
		if ( empty( $url ) ) {
			$opts->resetOptToDefault( $key );
			$url = $opts->getOpt( $key );
		}
		if ( !empty( $url ) && !Services::Data()->isValidWebUrl( $url ) && strpos( $url, '/' ) !== 0 ) {
			$url = $this->getCon()->urls->forImage( $url );
			if ( empty( $url ) ) {
				$opts->resetOptToDefault( $key );
				$url = $this->getCon()->urls->forImage( $opts->getOpt( $key ) );
			}
		}

		return $url;
	}
}