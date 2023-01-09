<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpThemesUpdates extends Base {

	public const SLUG = 'wp_themes_updates';
	public const WEIGHT = 45;

	public function href() :string {
		return Services::WpGeneral()->getAdminUrl_Themes( true );
	}

	protected function isProtected() :bool {
		return $this->countUpdates() === 0;
	}

	public function title() :string {
		return __( 'Themes With Updates', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All available theme updates have been applied.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( 'There are %s theme updates waiting to be applied.', 'wp-simple-firewall' ), $this->countUpdates() );
	}

	private function countUpdates() :int {
		return count( Services::WpThemes()->getUpdates() );
	}
}