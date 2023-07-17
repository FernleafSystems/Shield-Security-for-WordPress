<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpThemesUpdates extends Base {

	public const SLUG = 'wp_themes_updates';
	public const WEIGHT = 3;

	public function hrefFull() :string {
		return Services::WpGeneral()->getAdminUrl_Themes( true );
	}

	protected function testIfProtected() :bool {
		return $this->countUpdates() === 0;
	}

	public function title() :string {
		return __( 'Themes With Updates', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All available theme updates have been applied.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		$count = $this->countUpdates();
		return _n(
			__( 'There is 1 theme update waiting to be applied.', 'wp-simple-firewall' ),
			sprintf( __( 'There are %s theme updates waiting to be applied.', 'wp-simple-firewall' ), $count ),
			$count
		);
	}

	private function countUpdates() :int {
		return \count( Services::WpThemes()->getUpdates() );
	}
}