<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\ThemeReinstall
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\ThemeReinstaller;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;

/**
 * @phpstan-import-type OperatorChromeActionInput from OperatorChromeContract
 */
class ThemeReinstallContextActionBuilder {

	private ThemeReinstaller $reinstaller;

	public function __construct( ?ThemeReinstaller $reinstaller = null ) {
		$this->reinstaller = $reinstaller ?? new ThemeReinstaller();
	}

	/**
	 * @return list<OperatorChromeActionInput>
	 */
	public function buildForThemeStylesheet( string $stylesheet, string $displayName = '' ) :array {
		$theme = $this->reinstaller->eligibleTheme( $stylesheet );
		if ( !$theme instanceof WpThemeVo ) {
			return [];
		}

		$displayName = \trim( $displayName );
		$name = $displayName !== '' ? $displayName : $this->themeDisplayName( $theme );

		return [
			[
				'kind'             => 'ajax',
				'label'            => __( 'Reinstall Theme', 'wp-simple-firewall' ),
				'type'             => 'update',
				'icon_class'       => 'bi bi-arrow-clockwise',
				'ajax_action_json' => OperatorChromeContract::encodeJson(
					ActionData::Build( ThemeReinstall::class, true, [
						'stylesheet' => $theme->stylesheet,
					] )
				),
				'confirm_text'     => \sprintf(
					__( 'Reinstall %s from WordPress.org?', 'wp-simple-firewall' ),
					$name
				),
				'processing_text'  => \sprintf(
					__( 'Reinstalling %s. Keep this page open until it completes.', 'wp-simple-firewall' ),
					$name
				),
			],
		];
	}

	private function themeDisplayName( WpThemeVo $theme ) :string {
		$name = \trim( (string)$theme->Name );
		return $name !== '' ? $name : $theme->stylesheet;
	}
}
