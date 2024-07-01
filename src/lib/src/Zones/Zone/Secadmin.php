<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Secadmin extends Base {

	public function components() :array {
		return [
			Component\SecadminEnabled::class,
			Component\SecadminWpOptions::class,
			Component\SecadminWpAdmins::class,
		];
	}

	public function description() :array {
		$name = self::con()->getHumanName();
		return [
			sprintf( __( "%s's Security Admin system provides an additional security layer to the normal WordPress admin.", 'wp-simple-firewall' ), $name ),
			sprintf( __( "By turning on the Security Admin system you protect the %s plugin itself from tampering or accidental changes by other WordPress admins.", 'wp-simple-firewall' ), $name ),
			\implode( ' ', [
				__( "The Security Admin system can also prevent similar tampering or accidental changes to core WordPress settings, such as the site URL, permalinks, default user role, etc.", 'wp-simple-firewall' ),
			] ),
		];
	}

	public function icon() :string {
		return 'chat-left-dots';
	}

	public function title() :string {
		return __( 'Security Admin', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Add an additional WP admin layer to protect core WordPress settings.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\ModuleSecadmin::class;
	}
}