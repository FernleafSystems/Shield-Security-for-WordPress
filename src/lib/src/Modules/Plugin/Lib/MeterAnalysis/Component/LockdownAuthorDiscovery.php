<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Options;

class LockdownAuthorDiscovery extends Base {

	public const SLUG = 'lockdown_author_discovery';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Lockdown();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isBlockAuthorDiscovery();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Lockdown();
		return $mod->isModOptEnabled() ? $this->link( 'block_author_discovery' ) : $this->link( 'enable_lockdown' );
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