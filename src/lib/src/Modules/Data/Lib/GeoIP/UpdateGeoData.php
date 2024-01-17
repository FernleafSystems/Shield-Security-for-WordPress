<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP;

use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IpMeta\{
	IpMetaRecord,
	LoadIpMeta,
	Ops as IPMetaDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class UpdateGeoData {

	use PluginControllerConsumer;
	use ThisRequestConsumer;

	private $seedCloudflare;

	public function __construct( bool $seedWithCloudflare = false ) {
		$this->seedCloudflare = $seedWithCloudflare;
	}

	public function run() :?IpMetaRecord {
		$req = $this->req;

		$metaRecord = $req->ip_meta_record instanceof IpMetaRecord ?
			$req->ip_meta_record
			: ( new LoadIpMeta() )->single( $req->ip );

		if ( empty( $metaRecord ) || $req->carbon->timestamp - $metaRecord->updated_at > \DAY_IN_SECONDS ) {

			$geoData = \array_intersect_key( \array_filter( $this->getIpGeoData() ), \array_flip( [
				'country_iso2',
				'asn'
			] ) );

			$dbh = self::con()->db_con->dbhIPMeta();
			if ( empty( $metaRecord ) ) {
				try {
					$ipRecord = ( new IPRecords() )->loadIP( $req->ip );
					/** @var IPMetaDB\Record $metaRecord */
					$metaRecord = $dbh->getRecord()->applyFromArray( $geoData );
					$metaRecord->ip_ref = $ipRecord->id;
					$dbh->getQueryInserter()->insert( $metaRecord );
				}
				catch ( \Exception $e ) {
				}
			}
			else {
				$dbh->getQueryUpdater()->updateById( $metaRecord->id, $geoData );
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