<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Secadmin extends Base {

	public function actions() :array {
		$con = self::con();

		$actions = parent::actions();
		if ( $con->comps->sec_admin->isEnabledSecAdmin() ) {
			$actions[ 'disable' ] = [
				'title'   => __( 'Disable Security Admin', 'wp-simple-firewall' ),
				'href'    => $con->plugin_urls->noncedPluginAction(
					SecurityAdminRemove::class,
					$con->plugin_urls->adminHome(),
					[
						'quietly' => '1',
					]
				),
				'icon'    => self::con()->svgs->raw( 'toggle-off' ),
				'classes' => [
					'btn-outline-warning',
				],
			];
		}
		return $actions;
	}

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
			sprintf( __( "The Security Admin system protects the %s plugin against tampering or accidental changes by other WordPress admins.", 'wp-simple-firewall' ), $name ),
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