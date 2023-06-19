<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Services\Services;

class SnapWordpress extends BaseSnap {

	public function snap() :array {
		return [
			'core'    => [
				'version' => Services::WpGeneral()->getVersion(),
			],
			'options' => [
				'permalinks_structure'         => get_option( 'permalink_structure' ),
				'wp_option_admin_email'        => get_option( 'admin_email' ),
				'wp_option_blogdescription'    => get_option( 'blogdescription' ),
				'wp_option_blogname'           => get_option( 'blogname' ),
				'wp_option_default_role'       => get_option( 'default_role' ),
				'wp_option_home'               => get_option( 'home' ),
				'wp_option_url'                => get_option( 'url' ),
				'wp_option_users_can_register' => get_option( 'users_can_register' ) == '0' ? 'off' : 'on',
			],
		];
	}
}