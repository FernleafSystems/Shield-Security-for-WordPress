<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\{
	DB\IPs\IPGeoVO,
	DB\IPs\IPRecords
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Lookup {

	const URL_REDIRECTLI = 'https://api.redirect.li/v1/ip/';
	use PluginControllerConsumer;
	use IpAddressConsumer;

	private $ips = [];

	private $reqCount = 0;

	public function lookupIp() :IPGeoVO {
		$ip = $this->getIP();
		// Small optimization so we don't SQL it every time.
		if ( isset( $this->ips[ $ip ] ) ) {
			return $this->ips[ $ip ];
		}

		try {
			if ( empty( $ip ) || !Services::IP()->isValidIp_PublicRemote( $ip ) ) {
				throw new \Exception( 'Not a valid public IP address' );
			}

			$ipRecord = ( new IPRecords() )
				->setMod( $this->getCon()->getModule_Data() )
				->loadIP( $this->getIP(), true );

			if ( is_null( $ipRecord->geo )
				 || Services::Request()->carbon()->subMonth()->timestamp > @$ipRecord->geo[ 'ts' ] ) {

				if ( $this->reqCount++ > 30 ) {
					throw new \Exception( 'Lookup limit reached.' );
				}

				$ipRecord->geo = $this->redirectliIpLookup();
				$this->getCon()
					 ->getModule_Data()
					 ->getDbH_IPs()
					 ->getQueryUpdater()
					 ->updateById( $ipRecord->id, [
						 'geo' => $ipRecord->getRawData()[ 'geo' ]
					 ] );
			}

			$geoData = $ipRecord->geo ?? [];
		}
		catch ( \Exception $e ) {
			$geoData = [];
		}

		return $this->ips[ $ip ] = ( new IPGeoVO() )->applyFromArray( $geoData );
	}

	/**
	 * @throws \Exception
	 */
	private function redirectliIpLookup() :array {
		$data = @json_decode(
			Services::HttpRequest()->getContent( self::URL_REDIRECTLI.$this->getIP() ), true
		);
		$data = ( empty( $data ) || !is_array( $data ) ) ? [] : $data;
		$data[ 'ts' ] = Services::Request()->carbon( true )->timestamp;
		return $data;
	}
}