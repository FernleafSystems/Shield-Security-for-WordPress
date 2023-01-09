<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class WpPluginsInactive extends Base {

	public const SLUG = 'wp_plugins_inactive';
	public const WEIGHT = 25;

	public function href() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins( true ), [
			'plugin_status' => 'inactive'
		] );
	}

	protected function isProtected() :bool {
		return $this->countInactive() === 0;
	}

	public function title() :string {
		return __( 'Inactive Plugins', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "All installed plugins appear to be active and in-use.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( 'There are %s inactive and unused plugins.', 'wp-simple-firewall' ), $this->countInactive() );
	}

	private function countInactive() :int {
		$WPP = Services::WpPlugins();
		return count( $WPP->getPlugins() ) - count( $WPP->getActivePlugins() );
	}
}