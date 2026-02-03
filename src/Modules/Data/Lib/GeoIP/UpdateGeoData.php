<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpMeta\{
	IpMetaRecord,
	LoadIpMeta,
	Ops as IPMetaDB
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;

class UpdateGeoData {

	use PluginControllerConsumer;
	use ThisRequestConsumer;

	private $seedCloudflare;

	public function __construct( bool $seedWithCloudflare = false ) {
		$this->seedCloudflare = $seedWithCloudflare;
	}

	public function run() :?IpMetaRecord {
		$req = $this->req;
		$meta = $req->ip_meta_record;
		if ( empty( $meta )
			 || empty( $meta->country_iso2 )
			 || $req->carbon->timestamp - $meta->geo_updated_at > \DAY_IN_SECONDS ) {

			$dataKeys = \array_flip( [
				'country_iso2',
				'asn'
			] );

			$geoData = \array_intersect_key( \array_filter( $this->getIpGeoData() ), $dataKeys );

			$dbh = self::con()->db_con->ip_meta;
			if ( empty( $meta ) ) {
				try {
					$ipRecord = ( new IPRecords() )->loadIP( $req->ip );
					/** @var IPMetaDB\Record $meta */
					$meta = $dbh->getRecord()->applyFromArray( $geoData );
					$meta->ip_ref = $ipRecord->id;
					$meta->geo_updated_at = $req->carbon->timestamp;
					$dbh->getQueryInserter()->insert( $meta );
				}
				catch ( \Exception $e ) {
				}
			}
			elseif ( !empty( \array_diff( $geoData, \array_intersect_key( $meta->getRawData(), $dataKeys ) ) ) ) {
				$geoData[ 'geo_updated_at' ] = $req->carbon->timestamp;
				$dbh->getQueryUpdater()->updateById( $meta->id, $geoData );
			}

			$req->ip_meta_record = ( new LoadIpMeta() )->single( $req->ip );
		}

		return $req->ip_meta_record;
	}

	/**
	 * @return array{country_iso2: string, timezone: string, asn: string}
	 */
	private function getIpGeoData() :array {
		$data = apply_filters( 'shield/get_ip_geo_data',
			$this->seedCloudflare ? ( new Providers\CloudFlare() )->setThisRequest( $this->req )->lookup() : [] );

		if ( \preg_match( '#^([A-Z]{2})$#i', $data[ 'country_iso2' ] ?? '' ) ) {
			$data[ 'country_iso2' ] = \strtoupper( $data[ 'country_iso2' ] );
		}
		else {
			$data[ 'country_iso2' ] = '';
		}

		if ( \preg_match( '#^(AS)?([0-9]+)$#i', $data[ 'asn' ] ?? '', $matches ) ) {
			$data[ 'asn' ] = $matches[ 2 ];
		}
		else {
			$data[ 'asn' ] = '';
		}

		return $data;
	}
}