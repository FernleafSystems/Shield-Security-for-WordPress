<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildActivityLogTableData;

class ActivityLogTableAction extends TableActionBase {

	use HandlesRequestMetaTableAction;

	public const SLUG = 'logtable_action';
	private const SUB_ACTION_GET_REQUEST_META = 'get_request_meta';

	protected function getSubActionHandlers() :array {
		return [
			self::SUB_ACTION_RETRIEVE_TABLE_DATA => fn() => $this->retrieveTableData(),
			self::SUB_ACTION_GET_REQUEST_META    => fn() => $this->getRequestMeta(),
		];
	}

	protected function getUnsupportedSubActionMessage( string $subAction ) :string {
		return $this->buildUnsupportedSubActionMessage( 'Activity Log', $subAction );
	}

	protected function retrieveTableData() :array {
		return $this->buildRetrieveTableDataResponse( new BuildActivityLogTableData() );
	}
}
