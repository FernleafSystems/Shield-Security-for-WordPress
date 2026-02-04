<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Labels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class WhitelabelCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return !self::con()->this_req->wp_is_wpcli && $this->isEnabled();
	}

	public function isEnabled() :bool {
		return self::con()->opts->optIs( 'whitelabel_enable', 'Y' );
	}

	protected function run() {
		$con = self::con();
		add_filter( $con->prefix( 'is_relabelled' ), '__return_true' );
		add_filter( $con->prefix( 'labels' ), [ $this, 'applyWhiteLabels' ], 200 );
		add_filter( 'plugin_row_meta', [ $this, 'removePluginMetaLinks' ], 200, 2 );
	}

	public function applyWhiteLabels( Labels $labels ) :Labels {
		$opts = self::con()->opts;

		// these are the old white labelling keys which will be replaced upon final release of white labelling.
		$name = $opts->optGet( 'wl_pluginnamemain' );
		if ( !empty( $name ) ) {
			$labels->Name = $name;
			$labels->Title = $name;
		}

		$companyName = $opts->optGet( 'wl_companyname' );
		if ( !empty( $companyName ) ) {
			$labels->Author = $companyName;
			$labels->AuthorName = $companyName;
		}

		$labels->MenuTitle = empty( $opts->optGet( 'wl_namemenu' ) ) ? $labels->Name : $opts->optGet( 'wl_namemenu' );

		if ( !empty( $opts->optGet( 'wl_description' ) ) ) {
			$labels->Description = $opts->optGet( 'wl_description' );
		}

		$homeURL = $opts->optGet( 'wl_homeurl' );
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
			if ( $opts->optChanged( $key ) && !$DP->isValidWebUrl( $this->constructImageURL( $key ) ) ) {
				$opts->optReset( $key );
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
			foreach ( \array_keys( self::con()->cfg->plugin_meta ) as $slug ) {
				unset( $pluginMeta[ $slug ] );
			}
		}
		return $pluginMeta;
	}

	/**
	 * We cater for 3 options:
	 * Full URL
	 * Relative path URL: i.e. starts with /
	 * Or Plugin image URL i.e. doesn't start with HTTP or /
	 */
	private function constructImageURL( string $key ) :string {
		$opts = self::con()->opts;

		$url = $opts->optGet( $key );
		if ( empty( $url ) ) {
			$opts->optReset( $key );
			$url = $opts->optGet( $key );
		}
		if ( !empty( $url ) && !Services::Data()->isValidWebUrl( $url ) && \strpos( $url, '/' ) !== 0 ) {
			$url = self::con()->urls->forImage( $url );
			if ( empty( $url ) ) {
				$opts->optReset( $key );
				$url = self::con()->urls->forImage( $opts->optGet( $key ) );
			}
		}

		return $url;
	}
}