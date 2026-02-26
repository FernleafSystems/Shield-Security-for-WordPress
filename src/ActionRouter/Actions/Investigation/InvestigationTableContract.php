<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation;

class InvestigationTableContract {

	public const REQ_KEY_SUB_ACTION = 'sub_action';
	public const REQ_KEY_TABLE_TYPE = 'table_type';
	public const REQ_KEY_SUBJECT_TYPE = 'subject_type';
	public const REQ_KEY_SUBJECT_ID = 'subject_id';
	public const REQ_KEY_TABLE_DATA = 'table_data';
	public const SUB_ACTION_RETRIEVE_TABLE_DATA = 'retrieve_table_data';
	public const TABLE_TYPE_ACTIVITY = 'activity';
	public const TABLE_TYPE_TRAFFIC = 'traffic';
	public const TABLE_TYPE_SESSIONS = 'sessions';
	public const TABLE_TYPE_FILE_SCAN_RESULTS = 'file_scan_results';
	public const SUBJECT_TYPE_USER = 'user';
	public const SUBJECT_TYPE_IP = 'ip';
	public const SUBJECT_TYPE_PLUGIN = 'plugin';
	public const SUBJECT_TYPE_THEME = 'theme';
	public const SUBJECT_TYPE_CORE = 'core';
}
