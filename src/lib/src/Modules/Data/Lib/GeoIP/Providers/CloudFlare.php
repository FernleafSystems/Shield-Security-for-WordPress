<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\Providers;

use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CloudFlare {

	use ThisRequestConsumer;

	public function lookup() :array {
		$geoData = [];
		$req = $this->req;
		if ( !empty( $req->request->server( 'HTTP_HOST' ) ) && !empty( $req->request->server( 'HTTP_CF_IPCOUNTRY' ) )
			 && $req->request->server[ 'HTTP_HOST' ] === \wp_parse_url( Services::WpGeneral()
																				->getWpUrl(), \PHP_URL_HOST ) ) {
			$geoData[ 'country_iso2' ] = $req->request->server[ 'HTTP_CF_IPCOUNTRY' ];
		}
		return $geoData;
	}
}