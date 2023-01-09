<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class WpPluginsUpdates extends Base {

	public const SLUG = 'wp_plugins_updates';
	public const WEIGHT = 45;

	public function href() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins( true ), [
			'plugin_status' => 'upgrade'
		] );
	}

	protected function isProtected() :bool {
		return $this->countUpdates() === 0;
	}

	public function title() :string {
		return __( 'Plugins With Updates', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All available plugin updates have been applied.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( 'There are %s plugin updates waiting to be applied.', 'wp-simple-firewall' ), $this->countUpdates() );
	}

	private function countUpdates() :int {
		return count( Services::WpPlugins()->getUpdates() );
	}
}