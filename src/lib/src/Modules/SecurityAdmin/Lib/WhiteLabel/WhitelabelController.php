<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\WhiteLabel;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Labels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class WhitelabelController {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return !self::con()->this_req->wp_is_wpcli && $this->isEnabled();
	}

	public function isEnabled() :bool {
		return self::con()->opts->optIs( 'whitelabel_enable', 'Y' ) && self::con()->comps->sec_admin->isEnabledSecAdmin();
	}

	protected function run() {
		$con = self::con();
		add_filter( $con->prefix( 'is_relabelled' ), '__return_true' );
		add_filter( $con->prefix( 'labels' ), [ $this, 'applyWhiteLabels' ], 200 );
		add_filter( 'plugin_row_meta', [ $this, 'removePluginMetaLinks' ], 200, 2 );

		if ( $this->opts()->isOpt( 'wl_hide_updates', 'Y' ) && is_admin()
			 && !Services::WpGeneral()->isCron() && !$con->isPluginAdmin() ) {

			if ( \in_array( Services::WpPost()->getCurrentPage(), [ 'plugins.php', 'update-core.php' ] ) ) {
				add_filter( 'site_transient_update_plugins', [ $this, 'hidePluginUpdatesFromUI' ] );
			}
			else {
				add_filter( 'wp_get_update_data', [ $this, 'adjustUpdateDataCount' ] );
			}
		}
	}

	public function applyWhiteLabels( Labels $labels ) :Labels {
		$opts = $this->opts();

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
			$labels->icon_url_16x16_grey = $urlIcon;
			$labels->icon_url_32x32 = $urlIcon;
		}

		$urlDashboardLogo = $this->constructImageURL( 'wl_dashboardlogourl' );
		if ( !empty( $urlDashboardLogo ) ) {
			$labels->icon_url_128x128 = $urlDashboardLogo;
		}

		$urlPageBanner = $this->constructImageURL( 'wl_login2fa_logourl' );
		if ( !empty( $urlPageBanner ) ) {
			$labels->url_img_pagebanner = $urlPageBanner;
			$labels->url_img_logo_small = $urlPageBanner;
		}

		$labels->url_secadmin_forgotten_key = $labels->AuthorURI;
		$labels->is_whitelabelled = true;

		return $labels;
	}

	/**
	 * Adjusts the available updates count so as not to include Shield updates if they're hidden
	 * @param array $updateData
	 * @return array
	 */
	public function adjustUpdateDataCount( $updateData ) {
		if ( Services::WpPlugins()->isUpdateAvailable( self::con()->base_file ) ) {
			$updateData[ 'counts' ][ 'total' ]--;
			$updateData[ 'counts' ][ 'plugins' ]--;
		}
		return $updateData;
	}

	/**
	 * Verify whitelabel images
	 */
	public function verifyUrls() {
		$opts = self::con()->opts;
		$DP = Services::Data();
		foreach ( [ 'wl_menuiconurl', 'wl_dashboardlogourl', 'wl_login2fa_logourl' ] as $key ) {
			$changed = \method_exists( $opts, 'optChanged' ) ?
				$opts->optChanged( $key ) : $this->opts()->isOptChanged( $key );
			if ( $changed && !$DP->isValidWebUrl( $this->constructImageURL( $key ) ) ) {
				\method_exists( $opts, 'optReset' ) ?
					$opts->optReset( $key ) : $this->opts()->resetOptToDefault( $key );
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
		if ( $pluginBaseFile == self::con()->base_file ) {
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
		unset( $plugins->response[ self::con()->base_file ] );
		return $plugins;
	}

	/**
	 * We cater for 3 options:
	 * Full URL
	 * Relative path URL: i.e. starts with /
	 * Or Plugin image URL i.e. doesn't start with HTTP or /
	 */
	private function constructImageURL( string $key ) :string {
		$optsCon = self::con()->opts;
		$useCon = \method_exists( $optsCon, 'optGet' );

		$url = $useCon ? $optsCon->optGet( $key ) : $this->opts()->getOpt( $key );
		if ( empty( $url ) ) {
			$useCon ? $optsCon->optReset( $key ) : $this->opts()->resetOptToDefault( $key );
			$url = $useCon ? $optsCon->optGet( $key ) : $this->opts()->getOpt( $key );
		}
		if ( !empty( $url ) && !Services::Data()->isValidWebUrl( $url ) && \strpos( $url, '/' ) !== 0 ) {
			$url = self::con()->urls->forImage( $url );
			if ( empty( $url ) ) {
				$useCon ? $optsCon->optReset( $key ) : $this->opts()->resetOptToDefault( $key );
				$url = self::con()->urls->forImage(
					$useCon ? $optsCon->optGet( $key ) : $this->opts()->getOpt( $key )
				);
			}
		}

		return $url;
	}
}