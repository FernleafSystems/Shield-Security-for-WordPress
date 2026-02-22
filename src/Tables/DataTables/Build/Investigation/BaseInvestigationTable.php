<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Base;

abstract class BaseInvestigationTable extends Base {

	protected string $subjectType = '';
	protected $subjectId = null;

	public function setSubject( string $subjectType, $subjectId ) :self {
		$this->subjectType = \strtolower( \trim( $subjectType ) );
		$this->subjectId = $subjectId;
		return $this;
	}

	protected function getSearchPanesData() :array {
		return [];
	}

	protected function getSubjectFilterColumns() :array {
		return [];
	}

	final protected function getColumnsToDisplay() :array {
		$subjectColumns = \array_flip( \array_filter( $this->getSubjectFilterColumns(), '\is_string' ) );
		return \array_values( \array_filter(
			$this->getColumnsToDisplayForInvestigation(),
			fn( string $columnSlug ) :bool => !isset( $subjectColumns[ $columnSlug ] )
		) );
	}

	abstract protected function getSourceBuilderClass() :string;

	protected function getOrderColumnSlug() :string {
		return $this->loadSourceOrderColumnSlug( $this->getSourceBuilderClass() );
	}

	protected function getColumnsToDisplayForInvestigation() :array {
		return $this->loadSourceColumnsToDisplay( $this->getSourceBuilderClass() );
	}

	protected function getColumnDefs() :array {
		return $this->loadSourceColumnDefs( $this->getSourceBuilderClass() );
	}

	protected function loadSourceColumnDefs( string $sourceBuilderClass ) :array {
		return ( new $sourceBuilderClass() )->exportColumnDefs();
	}

	protected function loadSourceColumnsToDisplay( string $sourceBuilderClass ) :array {
		return ( new $sourceBuilderClass() )->exportColumnsToDisplay();
	}

	protected function loadSourceOrderColumnSlug( string $sourceBuilderClass ) :string {
		return ( new $sourceBuilderClass() )->exportOrderColumnSlug();
	}
}
