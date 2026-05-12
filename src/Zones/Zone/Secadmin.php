<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\SecurityAdminDisableActionBuilder;

class Secadmin extends Base {

	public function actions() :array {
		$actions = parent::actions();
		$disableAction = ( new SecurityAdminDisableActionBuilder() )->buildZoneAction();
		if ( !empty( $disableAction ) ) {
			$actions[ 'disable' ] = $disableAction;
		}
		return $actions;
	}

	public function tooltip() :string {
		return 'Edit settings for the entire Security Admin zone';
	}

	public function components() :array {
		return [
			Component\SecadminEnabled::class,
			Component\SecadminWpOptions::class,
			Component\SecadminWpAdmins::class,
		];
	}

	public function description() :array {
		return [
			sprintf( __( "%s's Security Admin system provides an additional security layer to the normal WordPress admin.", 'wp-simple-firewall' ), self::con()->labels->Name ),
			sprintf( __( "The Security Admin system protects the %s plugin against tampering or accidental changes by other WordPress admins.", 'wp-simple-firewall' ), self::con()->labels->Name ),
			\implode( ' ', [
				__( "It can also prevent similar tampering or accidental changes to core WordPress settings, such as the site URL, permalinks, default user role, etc.", 'wp-simple-firewall' ),
			] ),
			__( "Perhaps one of its most powerful features is how it will prevent other admins from tampering with other admin accounts.", 'wp-simple-firewall' )
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

	/**
	 * @inheritDoc
	 */
	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleSecadmin::class;
	}
}
