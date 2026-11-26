<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class WpPluginsUpdates extends Base {

	public const SLUG = 'wp_plugins_updates';
	public const WEIGHT = 4;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	public function hrefFull() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins( true ), [
			'plugin_status' => 'upgrade'
		] );
	}

	protected function testIfProtected() :bool {
		return $this->countUpdates() === 0;
	}

	public function title() :string {
		return __( 'Plugins With Updates', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'All available plugin updates have been applied.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		$count = $this->countUpdates();
		return sprintf(
			/* translators: %s: number of plugin updates */
			_n( 'There is %s plugin update waiting to be applied.', 'There are %s plugin updates waiting to be applied.', $count, 'wp-simple-firewall' ),
			$count
		);
	}

	public function guidance() :array {
		return [
			'checks'   => __( 'Shield asks WordPress which of your installed plugins have a newer version available.', 'wp-simple-firewall' ),
			'why'      => [
				__( 'Out-of-date plugins are one of the most common ways WordPress sites are broken into. Updates are how the security holes found in them are closed.', 'wp-simple-firewall' ),
			],
			'steps'    => [
				__( "Take a backup of your site's files and database.", 'wp-simple-firewall' ),
				__( 'Go to Plugins and update each plugin that shows an update. Updating one at a time makes it easy to tell which one caused a problem.', 'wp-simple-firewall' ),
				__( 'Check that the main parts of your site still work.', 'wp-simple-firewall' ),
			],
			'who'      => __( 'Any administrator can do this.', 'wp-simple-firewall' ),
			'practice' => [
				__( 'Turn on auto-updates, from the Plugins page, for the plugins you trust.', 'wp-simple-firewall' ),
				__( 'Replace a plugin that no longer gets updates with one that does.', 'wp-simple-firewall' ),
			],
		];
	}

	private function countUpdates() :int {
		return \count( Services::WpPlugins()->getUpdates() );
	}
}
