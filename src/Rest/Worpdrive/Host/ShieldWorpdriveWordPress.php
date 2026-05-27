<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\WorpdriveClient\Host\WorpdriveWordPress;

class ShieldWorpdriveWordPress implements WorpdriveWordPress {

	public function plugins() :array {
		$enum = [];
		foreach ( Services::WpPlugins()->getPlugins() as $file => $p ) {
			$enum[ $file ] = [
				'name'    => $p[ 'Name' ] ?? '',
				'version' => $p[ 'Version' ] ?? '',
				'dir'     => \dirname( $file ),
				'active'  => (int)is_plugin_active( $file ),
			];
		}
		\ksort( $enum );
		return \array_values( $enum );
	}

	public function themes() :array {
		$enum = [];
		$active = Services::WpThemes()->getCurrent()->get_stylesheet();
		foreach ( Services::WpThemes()->getThemes() as $t ) {
			if ( $t instanceof \WP_Theme ) {
				$enum[ $t->get_stylesheet() ] = [
					'name'    => $t->get( 'Name' ),
					'dir'     => $t->get_stylesheet(),
					'version' => $t->get( 'Version' ),
					'active'  => $active === $t->get_stylesheet() ? 1 : 0,
				];
			}
		}
		\ksort( $enum );
		return \array_values( $enum );
	}

	public function homeUrl() :string {
		return Services::WpGeneral()->getHomeUrl();
	}

	public function wpUrl() :string {
		return Services::WpGeneral()->getWpUrl();
	}

	public function restUrl() :string {
		return rest_url();
	}

	public function contentUrl() :string {
		return content_url();
	}

	public function locale() :string {
		return get_locale();
	}

	public function timezoneString() :string {
		return wp_timezone_string();
	}

	public function isMultisite() :bool {
		return is_multisite();
	}

	public function version() :string {
		return \function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : \get_bloginfo( 'version' );
	}

	public function scriptFilename() :string {
		return (string)Services::Request()->server( 'SCRIPT_FILENAME' );
	}
}
