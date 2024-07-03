<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Modules;

class ModuleUsers extends ModuleBase {

	protected function getLegacyModSlug() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules::USERS;
	}
}