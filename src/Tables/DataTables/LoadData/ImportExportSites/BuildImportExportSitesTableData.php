<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData;

class BuildImportExportSitesTableData extends \FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData {

	protected function getSearchPanesDataBuilder() :BaseBuildSearchPanesData {
		return new BaseBuildSearchPanesData();
	}

	public function loadForRecords() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function countTotalRecords() :int {
		return $this->repository()->countAllRows();
	}

	protected function countTotalRecordsFiltered() :int {
		return $this->repository()->countFilteredRows( $this->searchText() );
	}

	protected function hasActiveFiltersForFilteredCount() :bool {
		return $this->searchText() !== '';
	}

	/**
	 * @param Record[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return \array_map( function ( Record $record ) :array {
			return [
				'rid'                       => $record->id,
				'url'                       => esc_html( $record->url ),
				'status'                    => esc_html( $record->status ),
				'queue_status'              => esc_html( $record->queue_status ),
				'last_ping_attempt'         => $this->formatTimestamp( $record->last_ping_attempt_at ),
				'last_ping_attempt_at'      => $record->last_ping_attempt_at,
				'last_ping_success'         => $this->formatTimestamp( $record->last_ping_success_at ),
				'last_ping_success_at'      => $record->last_ping_success_at,
				'last_ping_failure'         => $this->formatTimestamp( $record->last_ping_failure_at ),
				'last_ping_failure_at'      => $record->last_ping_failure_at,
				'last_export_request'       => $this->formatTimestamp( $record->last_export_request_at ),
				'last_export_request_at'    => $record->last_export_request_at,
				'last_export_success'       => $this->formatTimestamp( $record->last_export_success_at ),
				'last_export_success_at'    => $record->last_export_success_at,
				'last_export_failure'       => $this->formatTimestamp( $record->last_export_failure_at ),
				'last_export_failure_at'    => $record->last_export_failure_at,
				'last_ping_http_code'       => $record->last_ping_http_code,
				'last_export_result_code'   => esc_html( $record->last_export_result_code ),
				'consecutive_failures'      => $record->consecutive_failures,
				'details'                   => $this->formatDetails( $record ),
				'updated_at'                => $record->updated_at,
			];
		}, $records );
	}

	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		return $this->repository()->selectFilteredRows(
			$this->searchText(),
			$offset,
			empty( $limit ) ? 25 : $limit,
			$this->getOrderBy(),
			$this->getOrderDirection()
		);
	}

	private function formatTimestamp( int $ts ) :string {
		return $ts > 0 ? $this->getColumnContent_Date( $ts ) : '-';
	}

	private function formatDetails( Record $record ) :string {
		$details = [];
		if ( !empty( $record->last_ping_error ) ) {
			$details[] = sprintf( '%s: %s', __( 'Ping', 'wp-simple-firewall' ), esc_html( $record->last_ping_error ) );
		}
		if ( !empty( $record->last_export_error ) ) {
			$details[] = sprintf( '%s: %s', __( 'Export', 'wp-simple-firewall' ), esc_html( $record->last_export_error ) );
		}
		if ( !empty( $record->import_id ) ) {
			$details[] = sprintf( '%s: <code>%s</code>', __( 'Import ID', 'wp-simple-firewall' ), esc_html( $record->import_id ) );
		}
		return empty( $details ) ? '-' : \implode( '<br/>', $details );
	}

	private function searchText() :string {
		return \trim( (string)( $this->table_data[ 'search' ][ 'value' ] ?? '' ) );
	}

	private function repository() :SiteRepository {
		return new SiteRepository();
	}
}
