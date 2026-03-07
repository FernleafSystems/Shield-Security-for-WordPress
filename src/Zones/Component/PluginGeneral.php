<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Services\Services;

class PluginGeneral extends Base {

	public function title() :string {
		return __( 'General Plugin Configuration', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Configure non-security related options of the plugin.', 'wp-simple-firewall' );
	}

	protected function status() :array {
		$status = parent::status();
		$badgeEnabled = self::con()->opts->optIs( 'display_plugin_badge', 'Y' );
		$source = Services::Request()->getIpDetector()->getPublicRequestSource();

		$status[ 'level' ] = EnumEnabledStatus::GOOD;
		if ( !$badgeEnabled && !self::con()->comps->whitelabel->isEnabled() ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( "The plugin security badge isn't being displayed to reassure visitors.", 'wp-simple-firewall' );
		}
		if ( !\in_array( $source, [ 'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP' ], true ) ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = sprintf( __( "Visitor IP addresses may be spoofed because the current source is %s.", 'wp-simple-firewall' ), sprintf( '<code>%s</code>', $source ) );
		}
		if ( empty( $status[ 'exp' ] ) ) {
			$status[ 'exp' ][] = __( 'Plugin-level operational settings are configured appropriately.', 'wp-simple-firewall' );
		}
		return $status;
	}
}
