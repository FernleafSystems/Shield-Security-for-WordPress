<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

class BaseZoneReportPluginsThemes extends BaseZoneReport {

	protected function processDiffAdded( array $item ) :array {
		$rows = [
			\sprintf( 'Newly installed (v%s)', esc_html( $item[ 'version' ] ) ),
		];
		if ( $item[ 'is_active' ] ) {
			$rows[] = __( 'Activated', 'wp-simple-firewall' );
		}
		return $rows;
	}

	protected function processDiffRemoved( array $item ) :array {
		return [
			\sprintf( 'Uninstalled (v%s)', esc_html( $item[ 'version' ] ) )
		];
	}

	protected function processDiffChanged( array $old, array $new ) :array {
		$changes = [];

		if ( !$old[ 'has_update' ] && $new[ 'has_update' ] ) {
			$changes[] = sprintf( __( 'Upgrade available for v%s', 'wp-simple-firewall' ), esc_html( $old[ 'version' ] ) );
		}
		elseif ( $old[ 'has_update' ] && !$new[ 'has_update' ] ) {
//			$changes[] = sprintf( __( 'Upgrade unavailable for v%s', 'wp-simple-firewall' ), esc_html( $old[ 'version' ] ) );
		}

		$versionCompare = \version_compare( $old[ 'version' ], $new[ 'version' ] );
		if ( $versionCompare === -1 ) {
			$changes[] = \sprintf( 'Upgraded to v%s', esc_html( $new[ 'version' ] ) );
		}
		elseif ( $versionCompare === 1 ) {
			$changes[] = \sprintf( 'Downgraded to v%s', esc_html( $new[ 'version' ] ) );
		}

		if ( $old[ 'is_active' ] && !$new[ 'is_active' ] ) {
			$changes[] = sprintf( __( 'Deactivated (v%s)', 'wp-simple-firewall' ), esc_html( $new[ 'version' ] ) );
		}
		elseif ( !$old[ 'is_active' ] && $new[ 'is_active' ] ) {
			$changes[] = sprintf( __( 'Activated (v%s)', 'wp-simple-firewall' ), esc_html( $new[ 'version' ] ) );
		}

		return $changes;
	}

	protected function getItemName( array $item ) :string {
		return $item[ 'name' ];
	}
}