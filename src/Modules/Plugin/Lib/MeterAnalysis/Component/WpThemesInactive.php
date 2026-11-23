<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpThemesInactive extends Base {

	public const SLUG = 'wp_themes_inactive';
	public const WEIGHT = 2;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	public function hrefFull() :string {
		return Services::WpGeneral()->getAdminUrl_Themes( true );
	}

	/**
	 * One spare theme is commonly kept, such as a default Twenty-something theme to fall back on, so only
	 * more than one inactive theme needs attention.
	 */
	protected function testIfProtected() :bool {
		return $this->countInactive() <= 1;
	}

	public function title() :string {
		return __( 'Inactive Themes', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return $this->countInactive() === 1
			? __( 'There is one inactive theme, which is fine to keep as a fallback.', 'wp-simple-firewall' )
			: __( 'There are no inactive themes.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		$count = $this->countInactive();
		return _n(
			__( 'There is 1 unused theme that should be uninstalled.', 'wp-simple-firewall' ),
			sprintf( __( 'There are %s unused themes that should be uninstalled.', 'wp-simple-firewall' ), $count ),
			$count
		, 'wp-simple-firewall' );
	}

	private function countInactive() :int {
		$WPT = Services::WpThemes();
		return \count( $WPT->getThemes() ) - ( $WPT->isActiveThemeAChild() ? 2 : 1 );
	}
}
