<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\CloakedPluginState;

class CloakedPluginIgnore extends CloakedPluginIgnoreBase {

	public const SLUG = 'cloaked_plugin_ignore';

	protected function applyIdentityChange( CloakedPluginState $state, string $identity, array $currentFindings ) :bool {
		return $state->ignoreIdentity( $identity, $currentFindings );
	}

	protected function successMessage() :string {
		return __( 'Cloaked plugin ignored.', 'wp-simple-firewall' );
	}
}
