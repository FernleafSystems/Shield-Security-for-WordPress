<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;

class BuildImportExportSitesTableData extends \FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData {

	private ?SiteSyncStatusBuilder $statusBuilder = null;

	protected function getSearchPanesDataBuilder() :BuildSearchPanesData {
		return new BuildSearchPanesData();
	}

	public function loadForRecords() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	/**
	 * @return array{
	 *   options:array{
	 *     sync_state:list<array{label:string,value:string}>,
	 *     status_key:list<array{label:string,value:string}>,
	 *     queue_status_key:list<array{label:string,value:string}>
	 *   }
	 * }
	 */
	protected function getSearchPanesData() :array {
		return $this->getSearchPanesDataBuilder()->build();
	}

	protected function countTotalRecords() :int {
		return $this->repository()->countAllRows();
	}

	protected function countTotalRecordsFiltered() :int {
		return $this->repository()->countFilteredRows( $this->searchText(), $this->buildWheresFromSearchParams() );
	}

	protected function hasActiveFiltersForFilteredCount() :bool {
		return $this->searchText() !== '' || !empty( $this->buildWheresFromSearchParams() );
	}

	/**
	 * @param Record[] $records
	 * @return list<array{
	 *   rid:int,
	 *   url:string,
	 *   profile:string,
	 *   status:string,
	 *   status_key:string,
	 *   queue_status:string,
	 *   queue_status_key:string,
	 *   sync_status:string,
	 *   sync_state:string,
	 *   actions:string,
	 *   updated_at:int
	 * }>
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		$statusBuilder = $this->statusBuilder();
		$profileLabels = $this->profileLabelsForRecords( $records );

		return \array_values( \array_map( function ( Record $record ) use ( $statusBuilder, $profileLabels ) :array {
			$syncStatus = $statusBuilder->build( $record );

			return [
				'rid'              => $record->id,
				'url'              => esc_html( $record->url ),
				'profile'          => $this->profileLabelForRecord( $record, $profileLabels ),
				'status'           => $statusBuilder->registrationHtml( $record->status ),
				'status_key'       => $record->status,
				'queue_status'     => $statusBuilder->queueHtml( $record->queue_status ),
				'queue_status_key' => $record->queue_status,
				'sync_status'      => $syncStatus[ 'summary_html' ],
				'sync_state'       => $syncStatus[ 'state_key' ],
				'actions'          => $this->actionsHtml( $record->id, $syncStatus[ 'state_key' ] ),
				'updated_at'       => $record->updated_at,
			];
		}, $records ) );
	}

	/**
	 * @param Record[] $records
	 * @return array<int,string>
	 */
	private function profileLabelsForRecords( array $records ) :array {
		return ( new ProfileRepository() )->profileLabelsForSites( $records );
	}

	/**
	 * @param array<int,string> $profileLabels
	 */
	private function profileLabelForRecord( Record $record, array $profileLabels ) :string {
		return esc_html( $profileLabels[ (int)$record->profile_ref ] );
	}

	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		return $this->repository()->selectFilteredRows(
			$this->searchText(),
			$offset,
			empty( $limit ) ? 25 : $limit,
			$this->getOrderBy(),
			$this->getOrderDirection(),
			$wheres
		);
	}

	/**
	 * @return array{
	 *   sync_state?:list<string>,
	 *   status_key?:list<string>,
	 *   queue_status_key?:list<string>
	 * }
	 */
	protected function validateSearchPanes( array $searchPanes ) :array {
		$statusBuilder = $this->statusBuilder();
		$validated = [];

		foreach ( $searchPanes as $column => $values ) {
			$values = \is_array( $values ) ? $values : [];
			switch ( $column ) {
				case 'sync_state':
					$validated[ $column ] = $statusBuilder->validStateKeys( $values );
					break;
				case 'status_key':
					$validated[ $column ] = $statusBuilder->validRegistrationStatuses( $values );
					break;
				case 'queue_status_key':
					$validated[ $column ] = $statusBuilder->validQueueStatuses( $values );
					break;
				default:
					break;
			}
		}

		return \array_filter( $validated );
	}

	/**
	 * @return list<string>
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		$statusBuilder = $this->statusBuilder();

		foreach ( \array_filter( $this->table_data[ 'searchPanes' ] ?? [] ) as $column => $selected ) {
			$selected = \is_array( $selected ) ? $selected : [];
			switch ( $column ) {
				case 'sync_state':
					$wheres[] = $statusBuilder->sqlWhereForStates( $selected );
					break;
				case 'status_key':
					$wheres[] = $statusBuilder->sqlWhereForRegistrationStatuses( $selected );
					break;
				case 'queue_status_key':
					$wheres[] = $statusBuilder->sqlWhereForQueueStatuses( $selected );
					break;
				default:
					break;
			}
		}

		return \array_values( \array_filter( $wheres ) );
	}

	private function searchText() :string {
		return \trim( (string)( $this->table_data[ 'search' ][ 'value' ] ?? '' ) );
	}

	private function statusBuilder() :SiteSyncStatusBuilder {
		return $this->statusBuilder ??= new SiteSyncStatusBuilder();
	}

	private function actionsHtml( int $id, string $syncState ) :string {
		$actions = [];
		if ( $syncState === SiteSyncStatusBuilder::STATE_PROBLEM ) {
			$label = esc_attr( __( 'Repair Connection', 'wp-simple-firewall' ) );
			$actions[] = sprintf(
				'<button type="button" class="btn btn-link text-warning p-0 import-export-site-repair" title="%1$s" aria-label="%1$s" data-rid="%2$d" data-import-export-site-repair="1"><i class="bi bi-wrench" aria-hidden="true"></i></button>',
				$label,
				$id
			);
		}

		$label = esc_attr( __( 'Remove site', 'wp-simple-firewall' ) );
		$actions[] = sprintf(
			'<button type="button" class="btn btn-link text-danger p-0 import-export-site-delete" title="%1$s" aria-label="%1$s" data-rid="%2$d" data-import-export-site-delete="1"><i class="bi bi-trash3" aria-hidden="true"></i></button>',
			$label,
			$id
		);

		return sprintf(
			'<div class="d-inline-flex align-items-center gap-2">%s</div>',
			\implode( '', $actions )
		);
	}

	private function repository() :SiteRepository {
		return new SiteRepository();
	}
}
