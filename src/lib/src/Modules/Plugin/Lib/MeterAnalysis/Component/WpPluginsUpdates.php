<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class WpPluginsUpdates extends Base {

	public const SLUG = 'wp_plugins_updates';
	public const WEIGHT = 4;

	public function hrefFull() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins( true ), [
			'plugin_status' => 'upgrade'
		] );
	}

	protected function testIfProtected() :bool {
		return $this->countUpdates() === 0;
	}

	public function title() :string {
		return __( 'Plugins With Updates', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All available plugin updates have been applied.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		$count = $this->countUpdates();
		return _n(
			__( 'There is 1 plugin update waiting to be applied.', 'wp-simple-firewall' ),
			sprintf( __( 'There are %s plugin updates waiting to be applied.', 'wp-simple-firewall' ), $count ),
			$count
		);
	}

	private function countUpdates() :int {
		return \count( Services::WpPlugins()->getUpdates() );
	}
}