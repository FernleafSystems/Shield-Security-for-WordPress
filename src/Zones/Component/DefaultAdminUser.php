<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Services\Services;

class DefaultAdminUser extends Base {

	public function title() :string {
		return __( 'Default Admin User', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "Remove the default 'admin' username from privileged accounts.", 'wp-simple-firewall' );
	}

	protected function postureWeight() :int {
		return 1;
	}

	protected function status() :array {
		$status = parent::status();
		$adminUser = Services::WpUsers()->getUserByUsername( 'admin' );
		if ( !$adminUser instanceof \WP_User || !user_can( $adminUser, 'manage_options' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
			$status[ 'exp' ][] = __( "The default 'admin' user is no longer available.", 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "The default 'admin' user is still available.", 'wp-simple-firewall' );
		}
		return $status;
	}
}
