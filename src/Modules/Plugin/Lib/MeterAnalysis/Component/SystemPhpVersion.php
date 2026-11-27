<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class SystemPhpVersion extends Base {

	public const SLUG = 'system_php_version';
	public const WEIGHT = 5;
	/**
	 * The oldest version of PHP that still gets security fixes. It moves up by one at the end of each December.
	 */
	public const MIN_VERSION = '8.2';

	protected function testIfProtected() :bool {
		return Services::Data()->getPhpVersionIsAtLeast( self::MIN_VERSION );
	}

	protected function hrefFull() :string {
		return 'https://clk.shldscrty.com/helpshieldminimumrequirements';
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	public function title() :string {
		return __( 'PHP Version', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return sprintf(
			/* translators: %1$s: current PHP version, %2$s: minimum required version */
			__( 'WordPress is running on a recent version (%1$s) of PHP (at least %2$s).', 'wp-simple-firewall' ),
			Services::Data()->getPhpVersionCleaned(), self::MIN_VERSION );
	}

	public function guidance() :array {
		return [
			'checks'   => sprintf(
				/* translators: %1$s: current PHP version, %2$s: oldest PHP version that still gets security fixes */
				__( 'Shield compares the version of PHP your site currently runs on (%1$s) with the oldest version that still gets security fixes (%2$s).', 'wp-simple-firewall' ),
				Services::Data()->getPhpVersionCleaned(), self::MIN_VERSION ),
			'why'      => [
				__( "PHP is the language WordPress runs on. Each version stops getting security fixes after a few years, so holes found in it after that stay open. Newer versions are also faster.", 'wp-simple-firewall' ),
				sprintf(
					/* translators: %s: PHP version number */
					__( 'Every version of PHP older than %s is past that point. Critical security holes in those versions are not being patched, and never will be.', 'wp-simple-firewall' ),
					self::MIN_VERSION ),
			],
			'steps'    => [
				__( "Check that your plugins and theme work with a newer version of PHP. Their developers usually say in their changelog or support pages, and a staging copy of your site is the surest test.", 'wp-simple-firewall' ),
				__( "Take a backup of your site's files and database.", 'wp-simple-firewall' ),
				__( 'In your hosting control panel, find the PHP version setting and choose a version that PHP still supports.', 'wp-simple-firewall' ),
				__( 'Check that your site works. If something breaks, switch back, then update or replace whatever broke before trying again.', 'wp-simple-firewall' ),
			],
			'who'      => __( 'Your web host. Many hosts let you change it yourself from their control panel.', 'wp-simple-firewall' ),
			'practice' => [
				__( 'Move to a newer version of PHP before yours reaches the end of its support.', 'wp-simple-firewall' ),
				__( 'Try a new PHP version on a staging copy of your site first, if your host provides one.', 'wp-simple-firewall' ),
			],
		];
	}

	public function descUnprotected() :string {
		return \implode( ' ', [
			/* translators: %s: PHP version number */
			sprintf( __( 'WordPress is running an old version (%s) of PHP.', 'wp-simple-firewall' ),
				Services::Data()->getPhpVersionCleaned() ),
			/* translators: %s: PHP version number */
			sprintf( __( 'Versions older than PHP %s no longer get security fixes.', 'wp-simple-firewall' ), self::MIN_VERSION )
		] );
	}
}
