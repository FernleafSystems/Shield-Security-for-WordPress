<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Options;

class LockdownAuthorDiscovery extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'lockdown_author_discovery';

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_Lockdown();
		/** @var Options $opts */
		$opts = $mod->opts();
		return $mod->isModOptEnabled() && $opts->isBlockAuthorDiscovery();
	}

	protected function getOptConfigKey() :string {
		return 'block_author_discovery';
	}

	public function title() :string {
		return sprintf( '%s / %s',
			__( 'Username Fishing', 'wp-simple-firewall' ), __( 'Author Discovery', 'wp-simple-firewall' ) );
	}

	public function descProtected() :string {
		return __( 'The ability to fish for WordPress usernames is disabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "The ability to fish for WordPress usernames isn't blocked.", 'wp-simple-firewall' );
	}
}