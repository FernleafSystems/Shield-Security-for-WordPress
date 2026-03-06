<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Reports\BuildReportsTableData;

class ReportTableAction extends TableActionBase {

	public const SLUG = 'report_table_action';
	private const SUB_ACTION_DELETE = 'delete';

	protected function getSubActionHandlers() :array {
		return [
			self::SUB_ACTION_RETRIEVE_TABLE_DATA => fn() => $this->retrieveTableData(),
			self::SUB_ACTION_DELETE              => fn() => $this->deleteSelectedReports(),
		];
	}

	protected function getSubActionRequiredDataKeysMap() :array {
		return [
			self::SUB_ACTION_DELETE => [ 'rids' ],
		];
	}

	protected function getUnsupportedSubActionMessage( string $subAction ) :string {
		return $this->buildUnsupportedSubActionMessage( 'Reports', $subAction );
	}

	protected function isPageReloadOnFailure() :bool {
		return false;
	}

	private function retrieveTableData() :array {
		return $this->buildRetrieveTableDataResponse( new BuildReportsTableData() );
	}

	private function deleteSelectedReports() :array {
		$reportIDs = \array_values(
			\array_filter(
				\array_map( '\intval', $this->action_data[ 'rids' ] ?? [] ),
				fn( int $id ) => $id > 0
			)
		);
		if ( empty( $reportIDs ) ) {
			return [
				'success'     => false,
				'page_reload' => false,
				'message'     => __( 'No reports were selected for deletion.', 'wp-simple-firewall' ),
			];
		}

		$success = false;
		foreach ( $reportIDs as $reportID ) {
			$success = self::con()->db_con->reports->getQueryDeleter()->deleteById( $reportID ) || $success;
		}

		return [
			'success'     => $success,
			'page_reload' => false,
			'message'     => \count( $reportIDs ) === 1
				? __( 'Report deleted.', 'wp-simple-firewall' )
				: __( 'Selected Reports Deleted.', 'wp-simple-firewall' ),
		];
	}
}
