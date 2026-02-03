<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsModules;

class ModuleBase extends \FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Base {

	public function title() :string {
		return ( new StringsModules() )->getFor( $this->getLegacyModSlug() )[ 'name' ];
	}

	public function subtitle() :string {
		return ( new StringsModules() )->getFor( $this->getLegacyModSlug() )[ 'subtitle' ];
	}

	protected function getLegacyModSlug() :string {
		return \explode( '_', static::Slug(), 2 )[ 1 ];
	}
}