<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class UserAdminExists extends Base {

	public const SLUG = 'user_admin_exists';
	public const WEIGHT = 1;

	protected function testIfProtected() :bool {
		$WPUsers = Services::WpUsers();
		$adminUser = $WPUsers->getUserByUsername( 'admin' );
		return !$adminUser instanceof \WP_User || !user_can( $adminUser, 'manage_options' );
	}

	public function hrefFull() :string {
		$WPUsers = Services::WpUsers();
		$adminUser = $WPUsers->getUserByUsername( 'admin' );
		return $adminUser instanceof \WP_User ? $WPUsers->getAdminUrl_ProfileEdit( $adminUser ) : '';
	}

	public function title() :string {
		return __( 'Default Admin User', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "The default 'admin' user is no longer available.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "The default 'admin' user is still available.", 'wp-simple-firewall' );
	}
}