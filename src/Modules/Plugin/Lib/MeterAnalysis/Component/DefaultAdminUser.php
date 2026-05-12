<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class DefaultAdminUser extends Base {

	public const SLUG = 'default_admin_user';
	public const WEIGHT = 1;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	protected function testIfProtected() :bool {
		$adminUser = Services::WpUsers()->getUserByUsername( 'admin' );
		return !$adminUser instanceof \WP_User || !user_can( $adminUser, 'manage_options' );
	}

	protected function hrefFull() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl( 'users.php' ), [
			's' => 'admin',
		] );
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	protected function text() :array {
		return \array_merge(
			parent::text(),
			[
				'fix' => __( 'Manage Users', 'wp-simple-firewall' ),
			]
		);
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
