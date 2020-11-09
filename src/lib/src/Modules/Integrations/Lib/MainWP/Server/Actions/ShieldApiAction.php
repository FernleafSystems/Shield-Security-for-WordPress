<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\{
	Common\Consumers\MWPSiteConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class ShieldApiAction {

	use ModConsumer;
	use MWPSiteConsumer;

	public function licenseCheck() :array {
		return $this->runAction( 'license_check' );
	}

	private function runAction( string $action, array $params = [] ) :array {
		return ( new Action() )
			->setMod( $this->getMod() )
			->setMwpSite( $this->getMwpSite() )
			->run( $action, $params );
	}
}
