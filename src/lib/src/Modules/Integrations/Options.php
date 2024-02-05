<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Options {

	public function isEnabledMainWP() :bool {
		return $this->isOpt( 'enable_mainwp', 'Y' );
	}
}