<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueScanResultScopeStateBuilder,
	ScanResultsDisplayOptions
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\IpAddressSql;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\BaseForScan;

/**
 * @phpstan-import-type ScanResultsDisplayNotice from ActionsQueueScanResultScopeStateBuilder
 * @property string                   $type
 * @property string                   $file
 * @property array<string,mixed>|null $results_display_options
 * @property string                   $scan_results_notice_context
 */
class BuildScanTableData extends \FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData {
	private bool $scanResultsChanged = false;
	private bool $scanResultsCountMemoizationReset = false;

	public function build(): array {
		$this->scanResultsChanged = false;
		$this->scanResultsCountMemoizationReset = false;
		$data = parent::build();
		$data[ 'scan_results_changed' ] = $this->scanResultsChanged;
		$data[ 'display_notice' ] = $this->buildDisplayNotice();
		return $data;
	}

	protected function getTotalCountCacheKey(): string {
		return '';
	}

	protected function getSearchPanesDataBuilder(): BuildSearchPanesData {
		return new BuildSearchPanesData();
	}

	protected function loadRecordsWithSearch(): array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData(): array {
		return [];
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ): array {
		return \array_values( $records );
	}

	/**
	 * The Wheres need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams(): array {
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

	protected function countTotalRecords(): int {
		return $this->getRecordsLoader()->countAll();
	}

	protected function countTotalRecordsFiltered(): int {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $this->buildWheresFromSearchParams();
		return $loader->countAll();
	}

	protected function getSearchableColumns(): array {
		// Use the DataTables definition builder to locate searchable columns
		return \array_filter( \array_map(
			fn( $column ) => ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '',
			( new BaseForScan() )->buildRaw()[ 'columns' ]
		) );
	}

	/**
	 * @return array[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ): array {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $wheres;
		$loader->limit = $limit;
		$loader->offset = $offset;
		$records = $loader->run();
		if ( $loader->hasScanResultsChanged() ) {
			$this->scanResultsChanged = true;
			$this->resetScanResultsCountMemoizationOnce();
		}
		return $records;
	}

	protected function getRecordsLoader(): LoadFileScanResultsTableData {
		$loader = new LoadFileScanResultsTableData();
		$loader->custom_record_retriever_wheres = ( new ScanResultsScopeResolver() )
			->wheresForActionScope( $this->type, $this->file );

		$explicitResultsDisplayOptions = $this->getExplicitResultsDisplayOptions();
		if ( $explicitResultsDisplayOptions !== null ) {
			$loader->results_display_options = $explicitResultsDisplayOptions;
		}

		$loader->order_dir = $this->getOrderDirection();
		$loader->order_by = $this->order_by;
		$loader->search_text = \preg_replace( '#[^/a-z\d_-]#i', '', (string)( $this->table_data[ 'search' ][ 'value' ] ?? '' ) );
		return $loader;
	}

	/**
	 * @return array<string,bool>|null
	 */
	private function getExplicitResultsDisplayOptions(): ?array {
		if ( !\is_array( $this->results_display_options ) ) {
			return null;
		}

		return ( new ScanResultsDisplayOptions() )->normalize( $this->results_display_options );
	}

	private function resetScanResultsCountMemoizationOnce() :void {
		if ( $this->scanResultsCountMemoizationReset ) {
			return;
		}

		self::con()->comps->scans->resetScanResultsCountMemoization();
		$this->scanResultsCountMemoizationReset = true;
	}

	/**
	 * @phpstan-return ScanResultsDisplayNotice
	 */
	private function buildDisplayNotice() :array {
		$scopeStateBuilder = new ActionsQueueScanResultScopeStateBuilder();
		if ( (string)( $this->scan_results_notice_context ?? '' )
			 !== ActionsQueueScanResultScopeStateBuilder::NOTICE_CONTEXT_ACTIONS_QUEUE ) {
			return $scopeStateBuilder->hiddenDisplayNotice();
		}

		try {
			return $scopeStateBuilder->buildForActionScope(
				(string)( $this->type ?? '' ),
				(string)( $this->file ?? '' ),
				$this->getExplicitResultsDisplayOptions() ?? ( new ScanResultsDisplayOptions() )->activeOnly(),
				true
			)[ 'display_notice' ];
		}
		catch ( \InvalidArgumentException $e ) {
			return $scopeStateBuilder->hiddenDisplayNotice();
		}
	}
}
