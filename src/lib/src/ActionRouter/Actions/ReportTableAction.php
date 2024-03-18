<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class ReportTableAction extends BaseAction {

	public const SLUG = 'report_table_action';

	protected function exec() {
		switch ( $this->action_data[ 'report_action' ] ) {
			case 'delete':
				$success = self::con()
					->db_con
					->reports
					->getQueryDeleter()
					->deleteById( (int)$this->action_data[ 'rid' ] );
				$msg = 'Report deleted.';
				break;
			default:
				$success = true;
				$msg = 'Invalid action';
				break;
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => $success
		];
	}
}