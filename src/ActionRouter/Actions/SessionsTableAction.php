<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\DeleteSession;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Sessions\BuildSessionsTableData;

class SessionsTableAction extends TableActionBase {

	public const SLUG = 'sessionstable_action';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}
	private const SUB_ACTION_DELETE = 'delete';

	protected function getSubActionHandlers() :array {
		return [
			self::SUB_ACTION_RETRIEVE_TABLE_DATA => fn() => $this->retrieveTableData(),
			self::SUB_ACTION_DELETE              => fn() => $this->deleteSelectedSessions(),
		];
	}

	protected function getUnsupportedSubActionMessage( string $subAction ) :string {
		return $this->buildUnsupportedSubActionMessage( 'Sessions', $subAction );
	}

	protected function isPageReloadOnFailure() :bool {
		return false;
	}

	protected function retrieveTableData() :array {
		return $this->buildRetrieveTableDataResponse( new BuildSessionsTableData() );
	}

	protected function deleteSelectedSessions() :array {
		( new DeleteSession() )->byShieldIDs( $this->fields()->stringList( 'rids' ) );
		return [
			'success'     => true,
			'page_reload' => false,
			'message'     => __( 'Selected Sessions Deleted.', 'wp-simple-firewall' ),
		];
	}
}
