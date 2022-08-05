<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPGeoVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions\CrowdSecRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\CleanDecisions_IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\CrowdSec\ForCrowdsecDecisions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;

class BuildIpRulesTableData extends BaseBuildTableData {

	/**
	 * @var CrowdSecRecord
	 */
	private $record;

	/**
	 * @var Lookup
	 */
	private $geoLookup;

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return ( new BuildSearchPanesData() )
			->setMod( $this->getMod() )
			->build();
	}

	/**
	 * @param array[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return array_values( array_filter( array_map(
			function ( $data ) {
				$geo = $this->getCountryIP( $data[ 'ip' ] );
				$data[ 'ip_linked' ] = $this->getColumnContent_LinkedIP( $data[ 'ip' ] );
				$data[ 'country' ] = empty( $geo->countryCode ) ?
					__( 'Unknown', 'wp-simple-firewall' ) : $geo->countryName;
				$data[ 'last_seen' ] = $this->getColumnContent_LastSeen( $data[ 'last_access_at' ] );
				$data[ 'auto_unblock_at' ] = $this->getColumnContent_UnblockedAt( $data[ 'auto_unblock_at' ] );
				$data[ 'created_since' ] = $this->getColumnContent_Date( $data[ 'created_at' ] );

				return $data;
			},
			$records
		) ) );
	}

	protected function countTotalRecords() :int {
		return $this->getRecordsLoader()->countAll();
	}

	protected function countTotalRecordsFiltered() :int {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $this->buildWheresFromSearchParams();
		return $loader->countAll();
	}

	/**
	 * The Wheres need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'ip':
						$wheres[] = sprintf( "`ips`.ip=INET6_ATON('%s')", array_pop( $selected ) );
						break;
					default:
						break;
				}
			}
		}
		return $wheres;
	}

	protected function getRecordsLoader() :Records\RecordsLoader {
		return ( new Records\RecordsLoader() )->setMod( $this->getMod() );
	}

	protected function getSearchableColumns() :array {
		// Use the DataTables definition builder to locate searchable columns
		return array_filter( array_map(
			function ( $column ) {
				return ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '';
			},
			( new ForCrowdsecDecisions() )
				->setMod( $this->getMod() )
				->buildRaw()[ 'columns' ]
		) );
	}

	/**
	 * @return CrowdSecRecord[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {

		( new CleanDecisions_IPs() )
			->setMod( $this->getMod() )
			->execute();

		$loader = $this->getRecordsLoader();
		$loader->wheres = $wheres;
		$loader->limit = empty( $limit ) ? 10 : $limit;
		$loader->offset = $offset;
		$loader->order_by = $this->getOrderBy();
		$loader->order_dir = $this->getOrderDirection();
		return $loader->loadRecords();
	}

	private function getColumnContent_LastSeen( int $ts ) :string {
		if ( empty( $ts ) ) {
			$content = __( 'Never Seen', 'wp-simple-firewall' );
		}
		else {
			$content = $this->getColumnContent_Date( $ts );
		}
		return $content;
	}

	private function getColumnContent_UnblockedAt( int $ts ) :string {
		if ( empty( $ts ) ) {
			$content = __( 'Never', 'wp-simple-firewall' );
		}
		else {
			$content = $this->getColumnContent_Date( $ts );
		}
		return $content;
	}

	private function getCountryIP( string $ip ) :IPGeoVO {
		if ( empty( $this->geoLookup ) ) {
			$this->geoLookup = ( new Lookup() )->setCon( $this->getCon() );
		}
		return $this->geoLookup
			->setIP( $ip )
			->lookupIp();
	}
}