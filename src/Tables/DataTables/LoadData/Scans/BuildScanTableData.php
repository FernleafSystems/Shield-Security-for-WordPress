<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\IpAddressSql;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\BaseForScan;

/**
 * @property string $type
 * @property string $file
 * @property array<string,mixed>|null $results_display_options
 */
class BuildScanTableData extends \FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData {

	protected function getTotalCountCacheKey() :string {
		return '';
	}

	protected function getSearchPanesDataBuilder() :BuildSearchPanesData {
		return new BuildSearchPanesData();
	}

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return [];
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return \array_values( $records );
	}

	/**
	 * The Wheres need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( \array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'ip':
						$wheres[] = IpAddressSql::equality( 'ips.ip', \array_pop( $selected ) );
						break;
					default:
						break;
				}
			}
		}
		return $wheres;
	}

	protected function countTotalRecords() :int {
		return $this->getRecordsLoader()->countAll();
	}

	protected function countTotalRecordsFiltered() :int {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $this->buildWheresFromSearchParams();
		return $loader->countAll();
	}

	protected function getSearchableColumns() :array {
		// Use the DataTables definition builder to locate searchable columns
		return \array_filter( \array_map(
			fn( $column ) => ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '',
			( new BaseForScan() )->buildRaw()[ 'columns' ]
		) );
	}

	/**
	 * @return array[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $wheres;
		$loader->limit = $limit;
		$loader->offset = $offset;
		return $loader->run();
	}

	protected function getRecordsLoader() :LoadFileScanResultsTableData {
		$loader = new LoadFileScanResultsTableData();
		$loader->custom_record_retriever_wheres = ( new ScanResultsScopeResolver() )
			->wheresForActionScope( $this->type, $this->file );

		$explicitResultsDisplayOptions = $this->getExplicitResultsDisplayOptions();
		if ( $explicitResultsDisplayOptions !== null ) {
			$loader->results_display_options = $explicitResultsDisplayOptions;
		}

		$loader->order_dir = $this->getOrderDirection();
		$loader->order_by = $this->order_by;
		$loader->search_text = \preg_replace( '#[^/a-z\d_-]#i', '', (string)$this->table_data[ 'search' ][ 'value' ] ?? '' );
		return $loader;
	}

	/**
	 * @return array<string,bool>|null
	 */
	private function getExplicitResultsDisplayOptions() :?array {
		if ( !\is_array( $this->results_display_options ) ) {
			return null;
		}

		return ( new \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScanResultsDisplayOptions() )
			->normalize( $this->results_display_options );
	}
}
