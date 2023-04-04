<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\License\ShieldLicense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless\Lookup;

class LookupRequest {

	use ModConsumer;

	public function lookup() :ShieldLicense {
		$lookup = new Lookup();
		$lookup->item_id = $this->opts()->getDef( 'license_item_id' );
		$lookup->install_id = $this->con()->getInstallationID()[ 'id' ];
		$lookup->url = $this->opts()->getMasterSiteLicenseURL();
		$lookup->nonce = ( new HandshakingNonce() )->create();
		$lookup->meta = [
			'version_shield' => $this->con()->getVersion(),
			'version_php'    => Services::Data()->getPhpVersionCleaned()
		];
		return ( new ShieldLicense() )->applyFromArray( $lookup->lookup()->getRawData() );
	}
}
