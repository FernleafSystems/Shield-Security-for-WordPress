<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic\BuildTrafficTableData;

class TrafficLogTableAction extends TableActionBase {

	public const SLUG = 'traffictable_action';

	protected function getSubActionHandlers() :array {
		return [
			self::SUB_ACTION_RETRIEVE_TABLE_DATA => fn() => $this->retrieveTableData(),
		];
	}

	protected function getUnsupportedSubActionMessage( string $subAction ) :string {
		return $this->buildUnsupportedSubActionMessage( 'Traffic Log', $subAction );
	}

	protected function retrieveTableData() :array {
		return $this->buildRetrieveTableDataResponse( new BuildTrafficTableData() );
	}
}
