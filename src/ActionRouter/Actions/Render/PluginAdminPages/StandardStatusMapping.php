<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

trait StandardStatusMapping {

	protected function standardStatusLabel( string $status ) :string {
		switch ( $status ) {
			case 'critical':
				return __( 'Critical', 'wp-simple-firewall' );
			case 'warning':
				return __( 'Warning', 'wp-simple-firewall' );
			default:
				return __( 'Good', 'wp-simple-firewall' );
		}
	}

	protected function standardStatusIconClass(
		string $status,
		string $warningIcon = 'exclamation-circle-fill',
		string $neutralIcon = 'info-circle-fill'
	) :string {
		switch ( $status ) {
			case 'critical':
				$icon = 'x-circle-fill';
				break;
			case 'warning':
				$icon = $warningIcon;
				break;
			case 'neutral':
				$icon = $neutralIcon;
				break;
			default:
				$icon = 'check-circle-fill';
				break;
		}
		return self::con()->svgs->iconClass( $icon );
	}
}
