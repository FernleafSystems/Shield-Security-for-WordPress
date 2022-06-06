<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\WhiteLabel;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 15.1
 */
class BuildOptions {

	use ModConsumer;

	public function build() :array {
		$opts = $this->getOptions();
		$main = $opts->getOpt( 'wl_pluginnamemain' );
		$menu = $opts->getOpt( 'wl_namemenu' );
		if ( empty( $menu ) ) {
			$menu = $main;
		}

		return [
			'name_main'            => $main,
			'name_menu'            => $menu,
			'name_company'         => $opts->getOpt( 'wl_companyname' ),
			'description'          => $opts->getOpt( 'wl_description' ),
			'url_home'             => $opts->getOpt( 'wl_homeurl' ),
			'url_icon'             => $this->buildWlImageUrl( 'wl_menuiconurl' ),
			'url_dashboardlogourl' => $this->buildWlImageUrl( 'wl_dashboardlogourl' ),
			'url_login2fa_logourl' => $this->buildWlImageUrl( 'wl_login2fa_logourl' ),
		];
	}

	/**
	 * We cater for 3 options:
	 * Full URL
	 * Relative path URL: i.e. starts with /
	 * Or Plugin image URL i.e. doesn't start with HTTP or /
	 * @param string $key
	 * @return string
	 */
	public function buildWlImageUrl( string $key ) {
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