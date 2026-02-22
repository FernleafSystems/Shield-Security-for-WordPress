<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForFileScanResults;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Services\Services;

class BuildFileScanResultsData extends BaseInvestigationData {

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSubjectWheres() :array {
		switch ( $this->subjectType ) {
			case 'plugin':
				$wheres = InvestigationSubjectWheres::forAssetSlug( (string)$this->subjectId );
				break;
			case 'theme':
				$slug = (string)$this->subjectId;
				$theme = Services::WpThemes()->getThemeAsVo( $slug );
				if ( !empty( $theme ) ) {
					$slug = (string)$theme->stylesheet;
				}
				$wheres = InvestigationSubjectWheres::forAssetSlug( $slug );
				break;
			case 'core':
				$wheres = InvestigationSubjectWheres::forCoreResults();
				break;
			default:
				$wheres = InvestigationSubjectWheres::impossible();
				break;
		}
		return $wheres;
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
			( new ForFileScanResults() )->buildRaw()[ 'columns' ]
		) );
	}

	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$loader = $this->getRecordsLoader( $wheres );
		$loader->offset = $offset;
		$loader->limit = $limit;
		return $loader->run();
	}

	private function getRecordsLoader( array $wheres ) :LoadFileScanResultsTableData {
		$loader = new LoadFileScanResultsTableData();
		$loader->order_dir = $this->getOrderDirection();
		$loader->order_by = $this->getOrderBy();
		$loader->search_text = \preg_replace( '#[^/a-z\d_-]#i', '', (string)( $this->table_data[ 'search' ][ 'value' ] ?? '' ) );
		$loader->custom_record_retriever_wheres = $wheres;
		return $loader;
	}
}
