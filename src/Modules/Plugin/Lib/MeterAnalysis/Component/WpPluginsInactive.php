<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class WpPluginsInactive extends Base {

	public const SLUG = 'wp_plugins_inactive';
	public const WEIGHT = 2;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	public function hrefFull() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins( true ), [
			'plugin_status' => 'inactive'
		] );
	}

	protected function testIfProtected() :bool {
		return $this->countInactive() === 0;
	}

	public function title() :string {
		return __( 'Inactive Plugins', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "All installed plugins appear to be active and in-use.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		$count = $this->countInactive();
		return sprintf(
			/* translators: %s: number of unused plugins */
			_n( 'There is %s unused plugin that should be uninstalled.', 'There are %s unused plugins that should be uninstalled.', $count, 'wp-simple-firewall' ),
			$count
		);
	}

	public function guidance() :array {
		return [
			'checks'   => __( 'Shield counts the plugins that are installed on this site but not activated.', 'wp-simple-firewall' ),
			'why'      => [
				__( "A deactivated plugin's files stay on your server, where they can still be requested directly. If one has a security hole, it can be used whether the plugin is active or not. It's also easy to overlook inactive plugin updates during maintenance work.", 'wp-simple-firewall' ),
			],
			'steps'    => [
				__( 'Go to Plugins and choose the Inactive filter.', 'wp-simple-firewall' ),
				__( "Delete each plugin you no longer need. Deleting removes the plugin's files, and some plugins remove their settings and data too.", 'wp-simple-firewall' ),
				__( "If you're keeping a plugin to use later, you can delete it now and install it again when you need it. Or ignore it here, so that Shield stops listing it.", 'wp-simple-firewall' ),
			],
			'who'      => __( 'Any administrator can do this.', 'wp-simple-firewall' ),
			'practice' => [
				__( 'Delete a plugin when you stop using it, rather than leaving it deactivated.', 'wp-simple-firewall' ),
				__( 'Take a backup before deleting a plugin whose data you might want back.', 'wp-simple-firewall' ),
			],
		];
	}

	private function countInactive() :int {
		return \count( Services::WpPlugins()->getPlugins() ) - \count( Services::WpPlugins()->getActivePlugins() );
	}
}
