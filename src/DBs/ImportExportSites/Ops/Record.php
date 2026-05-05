<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops;

/**
 * @property string $url
 * @property string $url_hash
 * @property string $import_id
 * @property string $source
 * @property string $status
 * @property string $queue_status
 * @property int    $priority
 * @property int    $queued_at
 * @property int    $picked_at
 * @property int    $lock_until
 * @property int    $next_ping_at
 * @property int    $expected_export_by
 * @property int    $last_ping_attempt_at
 * @property int    $last_ping_success_at
 * @property int    $last_ping_failure_at
 * @property int    $last_ping_http_code
 * @property string $last_ping_error
 * @property int    $last_export_request_at
 * @property int    $last_export_success_at
 * @property int    $last_export_failure_at
 * @property string $last_export_result_code
 * @property string $last_export_error
 * @property int    $ping_attempts_total
 * @property int    $consecutive_failures
 * @property array  $meta
 * @property int    $created_at
 * @property int    $updated_at
 * @property int    $deleted_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( \in_array( $key, [
			'url',
			'url_hash',
			'import_id',
			'source',
			'status',
			'queue_status',
			'last_ping_error',
			'last_export_result_code',
			'last_export_error',
		], true ) ) {
			$value = (string)$value;
		}
		elseif ( \in_array( $key, [
			'priority',
			'queued_at',
			'picked_at',
			'lock_until',
			'next_ping_at',
			'expected_export_by',
			'last_ping_attempt_at',
			'last_ping_success_at',
			'last_ping_failure_at',
			'last_ping_http_code',
			'last_export_request_at',
			'last_export_success_at',
			'last_export_failure_at',
			'ping_attempts_total',
			'consecutive_failures',
			'created_at',
			'updated_at',
			'deleted_at',
		], true ) ) {
			$value = (int)$value;
		}

		return $value;
	}
}
