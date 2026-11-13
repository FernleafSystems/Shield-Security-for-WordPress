<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Labels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class WhitelabelCon {
	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun(): bool {
		return !self::con()->this_req->wp_is_wpcli && $this->isEnabled();
	}

	public function isEnabled(): bool {
		return self::con()->opts->optIs( 'whitelabel_enable', 'Y' );
	}

	protected function run() {
		$con = self::con();
		add_filter( $con->prefix( 'is_relabelled' ), '__return_true' );
		add_filter( $con->prefix( 'labels' ), [ $this, 'applyWhiteLabels' ], 200 );
		add_filter( 'plugin_row_meta', [ $this, 'removePluginMetaLinks' ], 200, 2 );
	}

	/**
	 * The white-label plugin name as plain text.
	 */
	public function pluginName() :string {
		return $this->text( 'wl_pluginnamemain' );
	}

	/**
	 * The white-label home URL, only when it's a web address. Anything else, such as a javascript: URL, gives an
	 * empty string, whether it was saved before validation existed or arrived some other way.
	 */
	public function homeUrl() :string {
		$url = $this->savedHomeUrl();
		return Services::Data()->isValidWebUrl( $url ) ? $url : '';
	}

	/**
	 * The white-label dashboard logo, resolved to an address the same way the plugin's own labels use it.
	 */
	public function dashboardLogoUrl() :string {
		return $this->constructImageURL( 'wl_dashboardlogourl' );
	}

	private function savedHomeUrl() :string {
		return \trim( (string)self::con()->opts->optGet( 'wl_homeurl' ) );
	}

	/**
	 * A white-label name or description as plain text. These are placed into HTML all over the plugin, so any
	 * markup saved in them is removed before anything uses them.
	 */
	private function text( string $key ) :string {
		return sanitize_text_field( (string)self::con()->opts->optGet( $key ) );
	}

	public function applyWhiteLabels( Labels $labels ): Labels {
		// these are the old white labelling keys which will be replaced upon final release of white labelling.
		$name = $this->pluginName();
		if ( !empty( $name ) ) {
			$labels->Name = $name;
			$labels->Title = $name;
		}

		$companyName = $this->text( 'wl_companyname' );
		if ( !empty( $companyName ) ) {
			$labels->Author = $companyName;
			$labels->AuthorName = $companyName;
		}

		$menuName = $this->text( 'wl_namemenu' );
		$labels->MenuTitle = empty( $menuName ) ? $labels->Name : $menuName;

		$description = $this->text( 'wl_description' );
		if ( !empty( $description ) ) {
			$labels->Description = $description;
		}

		$homeURL = $this->homeUrl();
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

		$urlDashboardLogo = $this->dashboardLogoUrl();
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
	 * Verify the white-label images and home URL when they're saved.
	 */
	public function verifyUrls() {
		$opts = self::con()->opts;
		$DP = Services::Data();
		foreach ( [ 'wl_menuiconurl', 'wl_dashboardlogourl', 'wl_login2fa_logourl' ] as $key ) {
			if ( $opts->optChanged( $key ) && !$DP->isValidWebUrl( $this->constructImageURL( $key ) ) ) {
				$opts->optReset( $key );
			}
		}
		// The home URL is used as a link, so it must be a web address or nothing.
		if ( $opts->optChanged( 'wl_homeurl' ) && $this->savedHomeUrl() !== '' && $this->homeUrl() === '' ) {
			$opts->optReset( 'wl_homeurl' );
		}
	}

	/**
	 * @filter
	 * @param array|mixed  $pluginMeta
	 * @param string|mixed $pluginBaseFile
	 * @return array|mixed
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
	private function constructImageURL( string $key ): string {
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