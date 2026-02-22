<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\{
	BaseBuildSearchPanesData,
	BaseBuildTableData
};

abstract class BaseInvestigationData extends BaseBuildTableData {

	protected string $subjectType = '';
	protected $subjectId = null;

	public function setSubject( string $subjectType, $subjectId ) :self {
		$this->subjectType = \strtolower( \trim( $subjectType ) );
		$this->subjectId = $subjectId;
		return $this;
	}

	protected function getTotalCountCacheKey() :string {
		return '';
	}

	protected function getSearchPanesDataBuilder() :BaseBuildSearchPanesData {
		return new BaseBuildSearchPanesData();
	}

	abstract protected function getSubjectWheres() :array;

	protected function buildWheresFromInvestigationSearch() :array {
		return [];
	}

	protected function buildWheresFromSearchParams() :array {
		return \array_values( \array_filter( \array_merge(
			$this->buildWheresFromInvestigationSearch(),
			$this->getSubjectWheres()
		) ) );
	}
}
