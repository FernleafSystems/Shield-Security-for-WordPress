<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Secadmin extends Base {

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

	public function title() :string {
		return __( 'Security Admin', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleSecadmin::class;
	}
}
