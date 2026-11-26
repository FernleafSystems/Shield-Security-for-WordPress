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
		return sprintf(
			/* translators: %s: number of unused themes */
			_n( 'There is %s unused theme that should be uninstalled.', 'There are %s unused themes that should be uninstalled.', $count, 'wp-simple-firewall' ),
			$count
		);
	}

	public function guidance() :array {
		return [
			'checks'   => __( "Shield counts the themes that are installed but not in use. The parent of your active theme is in use. One spare theme is fine, as WordPress needs a theme to fall back on if the active one fails.", 'wp-simple-firewall' ),
			'why'      => [
				__( "An unused theme's files stay on your server, where they can still be requested directly. If one has a security hole, it can be used whether the theme is active or not.", 'wp-simple-firewall' ),
			],
			'steps'    => [
				__( 'Go to Appearance -> Themes.', 'wp-simple-firewall' ),
				__( "Select a theme you don't use to open its details, then choose Delete.", 'wp-simple-firewall' ),
				__( 'Keep one default WordPress theme as the fallback, and delete the rest.', 'wp-simple-firewall' ),
				__( "If you're keeping a theme to use later, you can delete it now and install it again when you need it. Or ignore it here, so that Shield stops listing it.", 'wp-simple-firewall' ),
			],
			'who'      => __( 'Any administrator can do this.', 'wp-simple-firewall' ),
			'practice' => [
				__( 'Keep the fallback theme updated like any other.', 'wp-simple-firewall' ),
			],
		];
	}

	private function countInactive() :int {
		$WPT = Services::WpThemes();
		return \count( $WPT->getThemes() ) - ( $WPT->isActiveThemeAChild() ? 2 : 1 );
	}
}
