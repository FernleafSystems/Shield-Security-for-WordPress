<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\License\ShieldLicense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless\Lookup;

class LookupRequest {

	use ModConsumer;

	public function lookup() :ShieldLicense {
		$con = $this->getCon();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$lookup = new Lookup();
		$lookup->item_id = $opts->getDef( 'license_item_id' );
		$lookup->install_id = $con->getInstallationID()[ 'id' ];
		$lookup->url = $opts->getMasterSiteLicenseURL();
		$lookup->nonce = ( new HandshakingNonce() )->setCon( $con )->create();
		$lookup->meta = [
			'version_shield' => $con->getVersion(),
			'version_php'    => Services::Data()->getPhpVersionCleaned()
		];
		$license = $lookup->lookup();

		return ( new ShieldLicense() )->applyFromArray( $license->getRawData() );
	}
}
