<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Services\Services;

class Lookup {

	const URL_REDIRECTLI = 'https://api.redirect.li/v1/ip/';
	use Databases\Base\HandlerConsumer;

	private $aIps = [];

	/**
	 * @param string $sIp
	 * @return Databases\GeoIp\EntryVO|null
	 */
	public function lookupIp( $sIp ) {
		// Small optimization so we don't SQL it every time.
		if ( isset( $this->aIps[ $sIp ] ) ) {
			return $this->aIps[ $sIp ];
		}

		/** @var Databases\GeoIp\Handler $oDbH */
		$oDbH = $this->getDbHandler();
		/** @var Databases\GeoIp\Select $oSel */
		$oSel = $oDbH->getQuerySelector();
		$oIp = $oSel->byIp( $sIp );

		/**
		 * We look up the IP and if the request fails, we store it anyway so that we don't repeatedly
		 * bombard the API. The address will eventually be expired over time and lookup will process
		 * again at a later date, as required
		 */
		if ( empty( $oIp ) ) {
			$oIp = new Databases\GeoIp\EntryVO();
			$oIp->ip = $sIp;
			$oIp->meta = $this->redirectliIpLookup( $sIp );
			/** @var Databases\GeoIp\Insert $oIsrt */
			$oDbH->getQueryInserter()->insert( $oIp );
		}

		$this->aIps[ $sIp ] = $oIp;
		return $oIp;
	}

	/**
	 * @param string $sIp
	 * @return array
	 */
	private function redirectliIpLookup( $sIp ) {
		$oHttp = Services::HttpRequest();
		$aIpData = @json_decode( $oHttp->getContent( self::URL_REDIRECTLI.$sIp ), true );
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