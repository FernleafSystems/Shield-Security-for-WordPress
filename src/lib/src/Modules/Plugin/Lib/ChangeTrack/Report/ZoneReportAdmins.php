<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Services\Services;

class ZoneReportAdmins extends BaseZoneReport {

	public function getZoneName() :string {
		return __( 'Admins' );
	}

	protected function processDiffAdded( array $item ) :array {
		return [
			\sprintf( '[%s] Admin account was added, or promoted to admin role', $item[ 'uniq' ] )
		];
	}

	protected function processDiffRemoved( array $item ) :array {
		if ( Services::WpUsers()->getUserById( $item[ 'uniq' ] instanceof \WP_User ) ) {
			$rows = [
				__( 'Admin account demoted from admin role', 'wp-simple-firewall' ),
			];
		}
		else {
			$rows = [
				__( 'Admin account was deleted', 'wp-simple-firewall' ),
			];
		}
		return $rows;
	}

	protected function processDiffChanged( array $old, array $new ) :array {
		$changes = [];
		if ( $old[ 'user_email' ] !== $new[ 'user_email' ] ) {
			$changes[] = __( 'Email address changed', 'wp-simple-firewall' );
		}
		if ( $old[ 'user_pass' ] !== $new[ 'user_pass' ] ) {
			$changes[] = __( 'Password updated', 'wp-simple-firewall' );
		}
		return $changes;
	}

	protected function getItemName( array $item ) :string {
		$WPU = Services::WpUsers();
		$user = $WPU->getUserById( $item[ 'uniq' ] );
		return $user instanceof \WP_User ? $user->user_login : __( 'Unknown User', 'wp-simple-firewall' );
	}

	protected function getItemLink( array $item ) :array {
		$WPU = Services::WpUsers();
		if ( empty( $WPU->getUserById( $item[ 'uniq' ] ) ) ) {
			$link = [
				'href' => Services::WpGeneral()->getAdminUrl( 'users.php' ),
				'text' => __( 'Users' ),
			];
		}
		else {
			$link = [
				'href' => $WPU->getAdminUrl_ProfileEdit( $item[ 'uniq' ] ),
				'text' => __( 'Profile' ),
			];
		}
		return $link;
	}
}