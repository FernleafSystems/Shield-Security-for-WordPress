<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CanScan {

	use ModConsumer;

	public function run() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$canScan = count( $req->getRawRequestParams( false ) ) > 0 && !empty( $req->getPath() );
		if ( $canScan ) {
			$paramsToScan = ( new ParametersToScan() )
				->setMod( $this->getMod() )
				->retrieve();
			$canScan = count( $paramsToScan ) > 0
					   && ( !$opts->isIgnoreAdmin() && is_super_admin() );
		}
		return $canScan;
	}
}