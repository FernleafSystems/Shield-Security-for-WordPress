<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class WpUpdates extends Base {

	public const SLUG = 'wp_updates';
	public const WEIGHT = 5;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	protected function testIfProtected() :bool {
		return !Services::WpGeneral()->hasCoreUpdate();
	}

	protected function hrefFull() :string {
		return Services::WpGeneral()->getAdminUrl_Updates();
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	public function title() :string {
		return __( 'WordPress Version', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "WordPress has all available upgrades applied.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "There is an upgrade available for WordPress.", 'wp-simple-firewall' );
	}

	public function guidance() :array {
		return [
			'checks'   => __( 'Shield asks WordPress whether a newer version of WordPress itself is available for this site.', 'wp-simple-firewall' ),
			'why'      => [
				__( "WordPress releases fix security holes, and those holes become public knowledge once the fix is out. A site left on an older version is open to attacks that have already been written.", 'wp-simple-firewall' ),
			],
			'steps'    => [
				__( "Take a backup of your site's files and database.", 'wp-simple-firewall' ),
				__( 'Go to Dashboard -> Updates and update WordPress.', 'wp-simple-firewall' ),
				__( 'Check that the main parts of your site still work: the home page, logging in, and the checkout if you have a shop.', 'wp-simple-firewall' ),
			],
			'who'      => __( 'Any administrator can do this. If your host manages WordPress updates for you, ask them when this one will be applied.', 'wp-simple-firewall' ),
			'practice' => [
				__( 'Leave automatic updates on for minor releases, which carry the security fixes. WordPress does this unless it has been switched off.', 'wp-simple-firewall' ),
				__( 'Move to each major release within a few weeks, after taking a backup.', 'wp-simple-firewall' ),
			],
		];
	}
}
