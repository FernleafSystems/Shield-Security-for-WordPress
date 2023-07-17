<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpThemesInactive extends Base {

	public const SLUG = 'wp_themes_inactive';
	public const WEIGHT = 2;

	public function hrefFull() :string {
		return Services::WpGeneral()->getAdminUrl_Themes( true );
	}

	protected function testIfProtected() :bool {
		return $this->countInactive() === 0;
	}

	public function title() :string {
		return __( 'Inactive Themes', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All installed themes appear to be active and in-use.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		$count = $this->countInactive();
		return _n(
			__( 'There is 1 unused theme that should be uninstalled.', 'wp-simple-firewall' ),
			sprintf( __( 'There are %s unused themes that should be uninstalled.', 'wp-simple-firewall' ), $count ),
			$count
		);
	}

	private function countInactive() :int {
		$WPT = Services::WpThemes();
		return \count( $WPT->getThemes() ) - ( $WPT->isActiveThemeAChild() ? 2 : 1 );
	}
}