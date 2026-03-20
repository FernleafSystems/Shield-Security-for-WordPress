<?php

$optionKey = 'shield_browser_fixture_actions_queue_detail';
$controller = \shield_security_get_plugin()->getController();
$argsList = \is_array( $args ?? null ) ? $args : [];
$action = (string)( $argsList[ 0 ] ?? '' );

if ( $action === '' ) {
	$argv = $_SERVER['argv'] ?? [];
	$dashdashIndex = \array_search( '--', $argv, true );
	if ( \is_int( $dashdashIndex ) ) {
		$action = (string)( $argv[ $dashdashIndex + 1 ] ?? '' );
	}
}

$cleanup = static function () use ( $controller, $optionKey ) :void {
	$ids = \get_option( $optionKey, [] );
	if ( !\is_array( $ids ) ) {
		$ids = [];
	}

	foreach ( [
		[ 'scan_result_item_meta', 'meta_id' ],
		[ 'scan_results', 'scan_result_id' ],
		[ 'scan_result_items', 'result_item_id' ],
		[ 'scans', 'scan_id' ],
	] as [ $dbKey, $idKey ] ) {
		$id = (int)( $ids[ $idKey ] ?? 0 );
		if ( $id > 0 ) {
			$controller->db_con->{$dbKey}
				->getQueryDeleter()
				->deleteById( $id );
		}
	}

	\delete_option( $optionKey );
};

switch ( $action ) {
	case 'cleanup':
		$cleanup();
		return;

	case 'seed':
		$cleanup();
		$controller->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp' ] )
			->store();

		global $wpdb;

		$scansDb = $controller->db_con->scans;
		$scanRecord = $scansDb->getRecord();
		$scanRecord->scan = 'afs';
		$scanRecord->ready_at = \time() - 60;
		$scanRecord->finished_at = \time();
		$scansDb->getQueryInserter()->insert( $scanRecord );
		$scanId = (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

		$resultItemsDb = $controller->db_con->scan_result_items;
		$item = $resultItemsDb->getRecord();
		$item->item_type = 'f';
		$item->item_id = 'wp-admin/admin.php';
		$resultItemsDb->getQueryInserter()->insert( $item );
		$resultItemId = (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

		$scanResultsDb = $controller->db_con->scan_results;
		$scanResult = $scanResultsDb->getRecord();
		$scanResult->scan_ref = $scanId;
		$scanResult->resultitem_ref = $resultItemId;
		$scanResultsDb->getQueryInserter()->insert( $scanResult );
		$scanResultId = (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

		$metaDb = $controller->db_con->scan_result_item_meta;
		$metaRecord = $metaDb->getRecord();
		$metaRecord->ri_ref = $resultItemId;
		$metaRecord->meta_key = 'is_in_core';
		$metaRecord->meta_value = 1;
		$metaDb->getQueryInserter()->insert( $metaRecord );
		$metaId = (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

		\update_option( $optionKey, [
			'scan_id' => $scanId,
			'result_item_id' => $resultItemId,
			'scan_result_id' => $scanResultId,
			'meta_id' => $metaId,
		], false );
		return;

	default:
		throw new \RuntimeException( 'Unknown Actions Queue detail fixture action: '.$action );
}
