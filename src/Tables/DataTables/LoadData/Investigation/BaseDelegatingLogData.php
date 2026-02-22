<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

abstract class BaseDelegatingLogData extends BaseInvestigationData {

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSubjectWheres() :array {
		switch ( $this->subjectType ) {
			case 'user':
				$wheres = InvestigationSubjectWheres::forUserColumn( '`req`.`uid`', (int)$this->subjectId );
				break;
			case 'ip':
				$wheres = InvestigationSubjectWheres::forIpColumn( '`ips`.`ip`', (string)$this->subjectId );
				break;
			default:
				$wheres = InvestigationSubjectWheres::impossible();
				break;
		}
		return $wheres;
	}

	protected function buildWheresFromInvestigationSearch() :array {
		return $this->getSource()->exportBuildWheresFromSearchParams();
	}

	protected function validateSearchPanes( array $searchPanes ) :array {
		return $this->getSource()->exportValidateSearchPanes( $searchPanes );
	}

	protected function countTotalRecords() :int {
		$loader = $this->getSource()->exportGetRecordsLoader();
		$loader->wheres = \array_merge( $loader->wheres ?? [], $this->getSubjectWheres() );
		return $loader->countAll();
	}

	protected function countTotalRecordsFiltered() :int {
		$loader = $this->getSource()->exportGetRecordsLoader();
		$loader->wheres = \array_merge( $loader->wheres ?? [], $this->buildWheresFromSearchParams() );
		return $loader->countAll();
	}

	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return $this->getSource()->exportBuildTableRowsFromRawRecords( $records );
	}

	protected function getSearchableColumns() :array {
		return $this->getSource()->exportGetSearchableColumns();
	}

	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		return $this->getSource()->exportGetRecords( $wheres, $offset, $limit );
	}

	abstract protected function getSource();
}
