<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\QueueScheduler;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites\BuildImportExportSitesTableData;

class ImportExportSitesTableAction extends TableActionBase {

	public const SLUG = 'importexport_sites_table_action';
	public const SUB_ACTION_QUEUE_SYNC = 'queue_sync';

	protected function getSubActionHandlers() :array {
		return [
			self::SUB_ACTION_RETRIEVE_TABLE_DATA => fn() => $this->retrieveTableData(),
			self::SUB_ACTION_QUEUE_SYNC          => fn() => $this->queueSync(),
		];
	}

	protected function getSubActionRequiredDataKeysMap() :array {
		return [
			self::SUB_ACTION_QUEUE_SYNC => [ 'rids' ],
		];
	}

	protected function getUnsupportedSubActionMessage( string $subAction ) :string {
		return $this->buildUnsupportedSubActionMessage( 'Import/Export Sites', $subAction );
	}

	protected function retrieveTableData() :array {
		return $this->buildRetrieveTableDataResponse( new BuildImportExportSitesTableData() );
	}

	protected function queueSync() :array {
		$count = ( new SiteRepository() )->queueSiteIds( \is_array( $this->action_data[ 'rids' ] ?? null ) ? $this->action_data[ 'rids' ] : [] );
		if ( $count > 0 ) {
			( new QueueScheduler() )->scheduleSoon();
		}
		return [
			'success'      => true,
			'table_reload' => true,
			'message'      => sprintf( _n( '%s site queued for sync.', '%s sites queued for sync.', $count, 'wp-simple-firewall' ), $count ),
		];
	}

	protected function isPageReloadOnFailure() :bool {
		return false;
	}
}
