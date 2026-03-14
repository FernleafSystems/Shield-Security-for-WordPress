<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class AbilityPermissions {

	use PluginControllerConsumer;

	/**
	 * @param mixed $input
	 * @return true|\WP_Error
	 */
	public function canExecute( $input = null ) {
		unset( $input );

		if ( !\function_exists( '\current_user_can' ) || !\current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'shield_mcp_permission_denied',
				__( 'Sorry, you are not allowed to execute Shield MCP abilities.', 'wp-simple-firewall' ),
				[ 'status' => \function_exists( '\rest_authorization_required_code' ) ? \rest_authorization_required_code() : 403 ]
			);
		}

		if ( !self::con()->caps->canRestAPILevel2() ) {
			return new \WP_Error(
				'shield_mcp_capability_unavailable',
				__( 'Shield MCP abilities require Shield REST API Level 2 capability.', 'wp-simple-firewall' ),
				[ 'status' => \function_exists( '\rest_authorization_required_code' ) ? \rest_authorization_required_code() : 403 ]
			);
		}

		return true;
	}
}
