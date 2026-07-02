<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites\BuildImportExportSitesTableData;

class ImportExportSitesTableAction extends TableActionBase {

	public const SLUG = 'importexport_sites_table_action';
	public const SUB_ACTION_QUEUE_SYNC = 'queue_sync';
	public const SUB_ACTION_DELETE_SITE = 'delete_site';
	public const SUB_ACTION_REPAIR_CONNECTION = 'repair_connection';

	protected function getSubActionHandlers() :array {
		return [
			self::SUB_ACTION_RETRIEVE_TABLE_DATA => fn() => $this->retrieveTableData(),
			self::SUB_ACTION_QUEUE_SYNC          => fn() => $this->queueSync(),
			self::SUB_ACTION_DELETE_SITE         => fn() => $this->deleteSite(),
			self::SUB_ACTION_REPAIR_CONNECTION   => fn() => $this->repairConnection(),
		];
	}

	protected function getSubActionRequiredDataKeysMap() :array {
		return [
			self::SUB_ACTION_QUEUE_SYNC        => [ 'rids' ],
			self::SUB_ACTION_DELETE_SITE       => [ 'rids' ],
			self::SUB_ACTION_REPAIR_CONNECTION => [ 'rids' ],
		];
	}

	protected function getUnsupportedSubActionMessage( string $subAction ) :string {
		return $this->buildUnsupportedSubActionMessage( 'Import/Export Sites', $subAction );
	}

	protected function retrieveTableData() :array {
		return $this->buildRetrieveTableDataResponse( new BuildImportExportSitesTableData() );
	}

	protected function queueSync() :array {
		$count = ( new ImportExportController() )->queueSitesForSync( $this->ridsFromActionData() );
		return [
			'success'      => true,
			'table_reload' => true,
			'message'      => sprintf( _n( '%s site queued for sync.', '%s sites queued for sync.', $count, 'wp-simple-firewall' ), $count ),
		];
	}

	protected function deleteSite() :array {
		$count = ( new ImportExportController() )->deleteSitesById( $this->ridsFromActionData() );
		if ( $count > 0 ) {
			\delete_transient( 'shield_dt_total_'.\md5( BuildImportExportSitesTableData::class ) );
		}
		$shouldReloadPage = $count > 0 && ( new SiteRepository() )->countActiveRows() === 0;
		return [
			'success'      => true,
			'table_reload' => !$shouldReloadPage,
			'page_reload'  => $shouldReloadPage,
			'message'      => sprintf( _n( '%s site removed.', '%s sites removed.', $count, 'wp-simple-firewall' ), $count ),
		];
	}

	protected function repairConnection() :array {
		$count = ( new ImportExportController() )->repairSitesById( $this->ridsFromActionData() );
		return [
			'success'      => true,
			'table_reload' => true,
			'message'      => sprintf( _n( '%s site queued for connection repair.', '%s sites queued for connection repair.', $count, 'wp-simple-firewall' ), $count ),
		];
	}

	private function ridsFromActionData() :array {
		$rids = $this->action_data[ 'rids' ] ?? [];
		return \is_array( $rids ) ? $rids : [];
	}

	protected function isPageReloadOnFailure() :bool {
		return false;
	}
}
