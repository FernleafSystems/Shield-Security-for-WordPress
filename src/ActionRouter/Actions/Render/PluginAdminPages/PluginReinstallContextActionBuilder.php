<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\PluginReinstall
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\PluginReinstaller;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

/**
 * @phpstan-import-type OperatorChromeActionInput from OperatorChromeContract
 */
class PluginReinstallContextActionBuilder {

	private PluginReinstaller $reinstaller;

	public function __construct( ?PluginReinstaller $reinstaller = null ) {
		$this->reinstaller = $reinstaller ?? new PluginReinstaller();
	}

	/**
	 * @return list<OperatorChromeActionInput>
	 */
	public function buildForPluginFile( string $file, string $displayName = '' ) :array {
		$plugin = $this->reinstaller->eligiblePlugin( $file );
		if ( !$plugin instanceof WpPluginVo ) {
			return [];
		}

		$displayName = \trim( $displayName );
		$name = $displayName !== '' ? $displayName : $this->pluginDisplayName( $plugin );

		return [
			[
				'kind'             => 'ajax',
				'label'            => __( 'Reinstall Plugin', 'wp-simple-firewall' ),
				'type'             => 'update',
				'icon_class'       => 'bi bi-arrow-clockwise',
				'ajax_action_json' => OperatorChromeContract::encodeJson(
					ActionData::Build( PluginReinstall::class, true, [
						'file' => $plugin->file,
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

	private function pluginDisplayName( WpPluginVo $plugin ) :string {
		$name = \trim( (string)( $plugin->Name ?: $plugin->Title ) );
		return $name !== '' ? $name : $plugin->file;
	}
}
