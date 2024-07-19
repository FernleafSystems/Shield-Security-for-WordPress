<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\BaseForScan;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $type
 * @property string $file
 */
class BuildScanTableData extends \FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData {

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
						$wheres[] = sprintf( "ips.ip=INET6_ATON('%s')", \array_pop( $selected ) );
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
			function ( $column ) {
				return ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '';
			},
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
		switch ( $this->type ) {
			case 'plugin':
				$loader->custom_record_retriever_wheres = [
					sprintf( "%s.`meta_key`='ptg_slug'", RetrieveBase::ABBR_RESULTITEMMETA ),
					sprintf( "%s.`meta_value`='%s'", RetrieveBase::ABBR_RESULTITEMMETA, $this->file ),
				];
				break;
			case 'theme':
				$theme = Services::WpThemes()->getThemeAsVo( $this->file );
				if ( !empty( $theme ) ) {
					$loader->custom_record_retriever_wheres = [
						sprintf( "%s.`meta_key`='ptg_slug'", RetrieveBase::ABBR_RESULTITEMMETA ),
						sprintf( "%s.`meta_value`='%s'", RetrieveBase::ABBR_RESULTITEMMETA, $theme->stylesheet ),
					];
				}
				break;
			case 'malware':
				$loader->custom_record_retriever_wheres = [
					sprintf( "%s.`meta_key`='is_mal'", RetrieveBase::ABBR_RESULTITEMMETA ),
					sprintf( "%s.`meta_value`=1", RetrieveBase::ABBR_RESULTITEMMETA ),
				];
				break;
			case 'wordpress':
			default:
				$loader->custom_record_retriever_wheres = [
					sprintf( "%s.`meta_key`='is_in_core'", RetrieveBase::ABBR_RESULTITEMMETA ),
					sprintf( "%s.`meta_value`=1", RetrieveBase::ABBR_RESULTITEMMETA ),
				];
				break;
		}

		$loader->order_dir = $this->getOrderDirection();
		$loader->order_by = $this->order_by;
		$loader->search_text = \preg_replace( '#[^/a-z\d_-]#i', '', (string)$this->table_data[ 'search' ][ 'value' ] ?? '' );
		return $loader;
	}
}