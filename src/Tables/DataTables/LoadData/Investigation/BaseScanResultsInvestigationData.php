<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\BaseInvestigationTable;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;

abstract class BaseScanResultsInvestigationData extends BaseInvestigationData {

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function countTotalRecords() :int {
		return $this->getRecordsLoader( $this->getSubjectWheres() )->countAll();
	}

	protected function countTotalRecordsFiltered() :int {
		return $this->getRecordsLoader( $this->buildWheresFromSearchParams() )->countAll();
	}

	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return \array_values( $records );
	}

	protected function getSearchableColumns() :array {
		return \array_filter( \array_map(
			fn( $column ) => ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '',
			( new ( $this->getInvestigationTableBuilderClass() )() )->buildRaw()[ 'columns' ]
		) );
	}

	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$loader = $this->getRecordsLoader( $wheres );
		$loader->offset = $offset;
		$loader->limit = $limit;
		return $loader->run();
	}

	/**
	 * @return class-string<BaseInvestigationTable>
	 */
	abstract protected function getInvestigationTableBuilderClass() :string;

	private function getRecordsLoader( array $wheres ) :LoadFileScanResultsTableData {
		$loader = new LoadFileScanResultsTableData();
		$loader->order_dir = $this->getOrderDirection();
		$loader->order_by = $this->getOrderBy();
		$loader->search_text = \preg_replace( '#[^/a-z\d_-]#i', '', (string)( $this->table_data[ 'search' ][ 'value' ] ?? '' ) );
		$loader->custom_record_retriever_wheres = $wheres;
		return $loader;
	}
}
