<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForSessions as ForSessionsTable;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Services\Services;

class BuildSessionsData extends BaseInvestigationData {

	private ?array $searchFilteredRows = null;
	private ?InvestigationSessionsTableData $source = null;
	private ?InvestigationStructuredSearch $structuredSearch = null;

	public function loadForRecords() :array {
		return $this->hasActiveSearchFilters()
			? $this->loadRecordsWithSearch()
			: $this->loadRecordsWithDirectQuery();
	}

	protected function loadRecordsWithSearch() :array {
		$results = $this->getSearchFilteredRows();
		$start = (int)( $this->table_data[ 'start' ] ?? 0 );
		$length = (int)( $this->table_data[ 'length' ] ?? 10 );

		if ( empty( $results ) || $start >= \count( $results ) ) {
			return [];
		}

		if ( $length < 0 ) {
			$results = \array_slice( $results, $start );
		}
		else {
			$results = \array_slice( $results, $start, $length );
		}

		return \array_values( $results );
	}

	protected function getSubjectWheres() :array {
		return $this->isSupportedSubject()
			? [ \sprintf( '`session`.`user_id`=%d', (int)$this->subjectId ) ]
			: [ '1=0' ];
	}

	protected function countTotalRecords() :int {
		return $this->isSupportedSubject() ? $this->getRecordsLoader()->count() : 0;
	}

	protected function countTotalRecordsFiltered() :int {
		return $this->hasActiveSearchFilters()
			? \count( $this->getSearchFilteredRows() )
			: $this->countTotalRecords();
	}

	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return $this->getSource()->exportBuildTableRowsFromRawRecords( $records );
	}

	protected function getSearchableColumns() :array {
		return \array_values( \array_filter( \array_map(
			fn( $column ) => ( $column[ 'searchable' ] ?? false ) ? ( $column[ 'data' ] ?? '' ) : '',
			( new ForSessionsTable() )
				->setSubject( $this->subjectType, $this->subjectId )
				->buildRaw()[ 'columns' ] ?? []
		), '\is_string' ) );
	}

	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		if ( !$this->isSupportedSubject() ) {
			return [];
		}
		return \array_slice(
			$this->getRecordsLoader()->allOrderedByLastActivityAt(),
			$offset,
			$limit > 0 ? $limit : null
		);
	}

	private function isSupportedSubject() :bool {
		return $this->subjectType === 'user' && (int)$this->subjectId > 0;
	}

	protected function getRecordsLoader() :LoadSessions {
		return new LoadSessions( (int)$this->subjectId );
	}

	private function hasActiveSearchFilters() :bool {
		return $this->getStructuredSearch()->hasActiveFilters( $this->parseSearchText() );
	}

	private function getSearchFilteredRows() :array {
		if ( $this->searchFilteredRows === null ) {
			$parsedSearch = $this->parseSearchText();

			if ( !$this->getStructuredSearch()->passesUserSubject(
				$parsedSearch,
				(int)$this->subjectId,
				fn( string $username ) :int => $this->getUserIdFromSearchUsername( $username ),
				fn( string $email ) :int => $this->getUserIdFromSearchEmail( $email )
			) ) {
				$this->searchFilteredRows = [];
				return $this->searchFilteredRows;
			}

			$records = $this->getRecords();
			$records = $this->getStructuredSearch()->filterRecordsForIpToken( $records, $parsedSearch );

			$rows = $this->buildTableRowsFromRawRecords( $records );
			$search = $parsedSearch[ 'remaining' ];
			$searchableColumns = \array_flip( $this->getSearchableColumns() );

			if ( empty( $search ) || empty( $searchableColumns ) ) {
				$this->searchFilteredRows = \array_values( $rows );
			}
			else {
				$this->searchFilteredRows = \array_values( \array_filter(
					$rows,
					function ( array $row ) use ( $search, $searchableColumns ) :bool {
						foreach ( \array_intersect_key( $row, $searchableColumns ) as $value ) {
							if ( \stripos( \wp_strip_all_tags( (string)$value ), (string)$search ) !== false ) {
								return true;
							}
						}
						return false;
					}
				) );
			}
		}
		return $this->searchFilteredRows;
	}

	protected function getUserIdFromSearchUsername( string $username ) :int {
		$user = Services::WpUsers()->getUserByUsername( $username );
		return empty( $user ) ? 0 : (int)$user->ID;
	}

	protected function getUserIdFromSearchEmail( string $email ) :int {
		$user = Services::WpUsers()->getUserByEmail( $email );
		return empty( $user ) ? 0 : (int)$user->ID;
	}

	private function getSource() :InvestigationSessionsTableData {
		if ( $this->source === null ) {
			$this->source = new InvestigationSessionsTableData();
		}
		$this->source->table_data = \is_array( $this->table_data ?? null ) ? $this->table_data : [];
		return $this->source;
	}

	private function getStructuredSearch() :InvestigationStructuredSearch {
		return $this->structuredSearch ??= new InvestigationStructuredSearch();
	}
}
