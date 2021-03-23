<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Lookup {

	const URL_REDIRECTLI = 'https://api.redirect.li/v1/ip/';
	use Databases\Base\HandlerConsumer;
	use IpAddressConsumer;

	private $ips = [];

	/**
	 * @return Databases\GeoIp\EntryVO|null
	 */
	public function lookupIp() {
		$ip = $this->getIP();
		// Small optimization so we don't SQL it every time.
		if ( isset( $this->ips[ $ip ] ) ) {
			return $this->ips[ $ip ];
		}

		/** @var Databases\GeoIp\Handler $dbh */
		$dbh = $this->getDbHandler();
		/** @var Databases\GeoIp\Select $select */
		$select = $dbh->getQuerySelector();
		$IP = $select->byIp( $ip );

		/**
		 * We look up the IP and if the request fails, we store it anyway so that we don't repeatedly
		 * bombard the API. The address will eventually be expired over time and lookup will process
		 * again at a later date, as required
		 */
		if ( empty( $IP ) ) {
			$IP = new Databases\GeoIp\EntryVO();
			$IP->ip = $ip;
			$IP->meta = $this->redirectliIpLookup();
			$dbh->getQueryInserter()->insert( $IP );
		}

		$this->ips[ $ip ] = $IP;
		return $IP;
	}

	private function redirectliIpLookup() :array {
		$oHttp = Services::HttpRequest();
		$aIpData = @json_decode( $oHttp->getContent( self::URL_REDIRECTLI.$this->getIP() ), true );
		if ( empty( $aIpData ) || !is_array( $aIpData ) ) {
			$aIpData = [];
		}

		return array_intersect_key(
			$aIpData,
			[
				'countryCode' => '',
				'countryName' => '',
				'timeZone'    => '',
				'latitude'    => '',
				'longitude'   => '',
			]
		);
	}
}