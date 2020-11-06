<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function isEnabledMainWP() :bool {
		return $this->isOpt( 'enable_mainwp', 'Y' );
	}
}