<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpThemesInactive extends Base {

	public const SLUG = 'wp_themes_inactive';
	public const WEIGHT = 25;

	public function href() :string {
		return Services::WpGeneral()->getAdminUrl_Themes( true );
	}

	protected function isProtected() :bool {
		return $this->countInactive() === 0;
	}

	public function title() :string {
		return __( 'Inactive Themes', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All installed themes appear to be active and in-use.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return sprintf( __( 'There are %s inactive and unused themes.', 'wp-simple-firewall' ), $this->countInactive() );
	}

	private function countInactive() :int {
		$WPT = Services::WpThemes();
		return count( $WPT->getThemes() ) - ( $WPT->isActiveThemeAChild() ? 2 : 1 );
	}
}