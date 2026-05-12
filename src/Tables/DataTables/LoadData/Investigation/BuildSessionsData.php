<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForSessions as ForSessionsTable;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Services\Services;

class BuildSessionsData extends BaseInvestigationData {

	private ?array $filteredRows = null;
	private ?array $subjectRecords = null;
	private ?InvestigationSessionsTableData $source = null;
	private ?InvestigationStructuredSearch $structuredSearch = null;

	public function loadForRecords() :array {
		$this->sanitizeTableSearchPanes();

		return $this->hasActiveSessionFilters()
			? $this->loadRecordsWithSearch()
			: $this->loadRecordsWithDirectQuery();
	}

	protected function loadRecordsWithSearch() :array {
		$results = $this->getFilteredRows();
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
		if ( $this->isUserSubject() ) {
			return [ \sprintf( '`session`.`user_id`=%d', (int)$this->subjectId ) ];
		}

		if ( $this->isIpSubject() ) {
			return [];
		}

		return [ '1=0' ];
	}

	protected function countTotalRecords() :int {
		return \count( $this->getSubjectRecords() );
	}

	protected function countTotalRecordsFiltered() :int {
		return \count( $this->getFilteredRows() );
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
		return \array_slice( $this->getSubjectRecords(), $offset, $limit > 0 ? $limit : null );
	}

	private function isSupportedSubject() :bool {
		return $this->isUserSubject() || $this->isIpSubject();
	}

	private function isUserSubject() :bool {
		return $this->subjectType === 'user' && (int)$this->subjectId > 0;
	}

	private function isIpSubject() :bool {
		return $this->subjectType === 'ip' && Services::IP()->isValidIp( (string)$this->subjectId );
	}

	protected function getRecordsLoader() :LoadSessions {
		return new LoadSessions( $this->isUserSubject() ? (int)$this->subjectId : null );
	}

	protected function hasActiveFiltersForFilteredCount() :bool {
		$this->sanitizeTableSearchPanes();
		return $this->hasActiveSessionFilters();
	}

	private function hasActiveSessionFilters() :bool {
		return $this->getSelectedSearchPaneUserId() > 0
			|| $this->getStructuredSearch()->hasActiveFilters( $this->parseSearchText() );
	}

	private function getFilteredRows() :array {
		if ( $this->filteredRows === null ) {
			$this->sanitizeTableSearchPanes();

			if ( !$this->isSupportedSubject() ) {
				$this->filteredRows = [];
				return $this->filteredRows;
			}

			$parsedSearch = $this->parseSearchText();
			$userIds = $this->getStructuredSearch()->resolveRequestedUserIds(
				$parsedSearch,
				fn( string $username ) :int => $this->getUserIdFromSearchUsername( $username ),
				fn( string $email ) :int => $this->getUserIdFromSearchEmail( $email )
			);
			$selectedUserId = $this->getSelectedSearchPaneUserId();

			if ( $this->isUserSubject() && !$this->getStructuredSearch()->passesUserSubject(
				$parsedSearch,
				(int)$this->subjectId,
				fn( string $username ) :int => $this->getUserIdFromSearchUsername( $username ),
				fn( string $email ) :int => $this->getUserIdFromSearchEmail( $email )
			) ) {
				$this->filteredRows = [];
				return $this->filteredRows;
			}

			if ( $this->isIpSubject() && $this->getStructuredSearch()->hasUserFilters( $parsedSearch ) && empty( $userIds ) ) {
				$this->filteredRows = [];
				return $this->filteredRows;
			}

			$records = $this->getSubjectRecords();
			if ( $selectedUserId > 0 ) {
				$records = \array_values( \array_filter(
					$records,
					fn( array $record ) :bool => $this->extractSessionUserId( $record ) === $selectedUserId
				) );
			}
			if ( $this->isIpSubject() && !empty( $userIds ) ) {
				$records = \array_values( \array_filter(
					$records,
					fn( array $record ) :bool => \in_array( $this->extractSessionUserId( $record ), $userIds, true )
				) );
			}
			$records = $this->getStructuredSearch()->filterRecordsForIpToken( $records, $parsedSearch );

			$rows = $this->buildTableRowsFromRawRecords( $records );
			$search = $parsedSearch[ 'remaining' ];
			$searchableColumns = \array_flip( $this->getSearchableColumns() );

			if ( empty( $search ) || empty( $searchableColumns ) ) {
				$this->filteredRows = \array_values( $rows );
			}
			else {
				$this->filteredRows = \array_values( \array_filter(
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
		return $this->filteredRows;
	}

	private function getSubjectRecords() :array {
		if ( $this->subjectRecords === null ) {
			if ( !$this->isSupportedSubject() ) {
				$this->subjectRecords = [];
				return $this->subjectRecords;
			}

			$records = $this->getRecordsLoader()->allOrderedByLastActivityAt();
			if ( $this->isIpSubject() ) {
				$subjectIp = (string)$this->subjectId;
				$records = \array_values( \array_filter(
					$records,
					fn( array $record ) :bool => $this->sessionMatchesIp( $record, $subjectIp )
				) );
			}
			$this->subjectRecords = \array_values( $records );
		}

		return $this->subjectRecords;
	}

	private function sessionMatchesIp( array $record, string $subjectIp ) :bool {
		$recordIp = $this->extractSessionIp( $record );
		return $recordIp !== '' && Services::IP()->IpIn( $subjectIp, [ $recordIp ] );
	}

	private function extractSessionIp( array $record ) :string {
		return \trim( (string)( $record[ 'ip' ] ?? $record[ 'shield' ][ 'ip' ] ?? '' ) );
	}

	private function extractSessionUserId( array $record ) :int {
		return (int)( $record[ 'shield' ][ 'user_id' ] ?? 0 );
	}

	private function getSelectedSearchPaneUserId() :int {
		$userId = \current( $this->table_data[ 'searchPanes' ][ 'uid' ] ?? [] );
		return empty( $userId ) ? 0 : (int)$userId;
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
