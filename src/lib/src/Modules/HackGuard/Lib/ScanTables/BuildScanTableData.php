<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\AuditTrail\ForAuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $type
 * @property string $file
 */
class BuildScanTableData extends BaseBuildTableData {

	protected function loadRecordsWithDirectQuery() :array {
		return $this->loadRecordsWithSearch();
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return array_values( $records );
	}

	/**
	 * The Wheres need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'event':
						if ( count( $selected ) > 1 ) {
							$wheres[] = sprintf( 'log.event_slug IN (`%s`)', implode( '`,`', $selected ) );
						}
						else {
							$wheres[] = sprintf( "log.event_slug='%s'", array_pop( $selected ) );
						}
						break;
					case 'ip':
						$wheres[] = sprintf( "ips.ip=INET6_ATON('%s')", array_pop( $selected ) );
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
		return array_filter( array_map(
			function ( $column ) {
				return ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '';
			},
			( new ForAuditTrail() )
				->setMod( $this->getMod() )
				->buildRaw()[ 'columns' ]
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
		$loader->order_dir = $this->getOrderDirection();
		return $loader->run();
	}

	/**
	 * @return TableData\BaseLoadTableData
	 */
	protected function getRecordsLoader() {
		switch ( $this->type ) {
			case 'plugin':
				$loader = new TableData\LoadTableDataPlugin( Services::WpPlugins()->getPluginAsVo( $this->file ) );
				break;
			case 'theme':
				$loader = new TableData\LoadTableDataTheme( Services::WpThemes()->getThemeAsVo( $this->file ) );
				break;
			case 'malware':
				$loader = new TableData\LoadTableDataMalware();
				break;
			case 'wordpress':
			default:
				$loader = new TableData\LoadTableDataWordpress();
				break;
		}
		return $loader->setMod( $this->getMod() );
	}
}