<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Malware\Ops\Handler as MalwareHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalwareStatus;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Static factory helpers for inserting test records via the DB handlers,
 * avoiding repetitive setup code across test classes.
 */
class TestDataFactory {

	private static function con() :Controller {
		return shield_security_get_plugin()->getController();
	}

	// ── IP Records ─────────────────────────────────────────────────

	/**
	 * Ensure an IP record exists and return it.
	 *
	 * @throws \Exception
	 */
	public static function createIpRecord( string $ip ) :\FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\Ops\Record {
		return ( new IPRecords() )->loadIP( $ip );
	}

	// ── IP Rules ───────────────────────────────────────────────────

	/**
	 * Insert a raw IP rule record and return its ID.
	 *
	 * @param array $overrides  Keys matching the IpRules\Ops\Record properties.
	 */
	public static function insertIpRule( string $ip, string $type, array $overrides = [] ) :int {
		$con = self::con();
		$dbh = $con->db_con->ip_rules;

		$ipRecord = self::createIpRecord( $ip );

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Record $record */
		$record = $dbh->getRecord();
		$record->ip_ref = $ipRecord->id;
		$record->type = $type;
		$record->cidr = $overrides[ 'cidr' ] ?? 32;
		$record->is_range = $overrides[ 'is_range' ] ?? false;
		$record->offenses = $overrides[ 'offenses' ] ?? 0;
		$record->label = $overrides[ 'label' ] ?? 'test';
		$record->blocked_at = $overrides[ 'blocked_at' ] ?? 0;
		$record->unblocked_at = $overrides[ 'unblocked_at' ] ?? 0;
		$record->last_access_at = $overrides[ 'last_access_at' ] ?? Services::Request()->ts();
		$record->can_export = $overrides[ 'can_export' ] ?? false;

		$dbh->getQueryInserter()->insert( $record );

		global $wpdb;
		return (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
	}

	/**
	 * Insert a manual-block rule for the given IP.
	 */
	public static function insertManualBlock( string $ip, array $overrides = [] ) :int {
		return self::insertIpRule( $ip, IpRulesHandler::T_MANUAL_BLOCK, \array_merge( [
			'blocked_at' => Services::Request()->ts(),
		], $overrides ) );
	}

	/**
	 * Insert an auto-block rule for the given IP.
	 */
	public static function insertAutoBlock( string $ip, array $overrides = [] ) :int {
		return self::insertIpRule( $ip, IpRulesHandler::T_AUTO_BLOCK, \array_merge( [
			'blocked_at'     => Services::Request()->ts(),
			'last_access_at' => Services::Request()->ts(),
		], $overrides ) );
	}

	/**
	 * Insert a bypass (whitelist) rule for the given IP.
	 */
	public static function insertBypass( string $ip, array $overrides = [] ) :int {
		return self::insertIpRule( $ip, IpRulesHandler::T_MANUAL_BYPASS, $overrides );
	}

	/**
	 * Insert a CrowdSec block rule for the given IP.
	 */
	public static function insertCrowdsecBlock( string $ip, array $overrides = [] ) :int {
		return self::insertIpRule( $ip, IpRulesHandler::T_CROWDSEC, \array_merge( [
			'blocked_at' => Services::Request()->ts(),
		], $overrides ) );
	}

	// ── Bot Signals ────────────────────────────────────────────────

	/**
	 * Insert a bot signal record for an IP.
	 *
	 * @param array $signals  Keys like 'notbot_at', 'bt404_at', etc. with timestamp values.
	 */
	public static function insertBotSignal( string $ip, array $signals = [] ) :int {
		$con = self::con();
		$dbh = $con->db_con->bot_signals;

		$ipRecord = self::createIpRecord( $ip );

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\Ops\Record $record */
		$record = $dbh->getRecord();
		$record->ip_ref = $ipRecord->id;
		foreach ( $signals as $field => $value ) {
			$record->{$field} = $value;
		}

		$dbh->getQueryInserter()->insert( $record );

		global $wpdb;
		return (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
	}

	// ── Activity Logs ──────────────────────────────────────────────

	/**
	 * Insert an activity log entry.
	 */
	public static function insertActivityLog( string $eventSlug, string $ip = '10.10.10.10', array $overrides = [] ) :int {
		$con = self::con();
		$dbh = $con->db_con->activity_logs;

		// Create IP + request log records to satisfy FK constraint: ips → req_logs → at_logs
		$ipRecord = self::createIpRecord( $ip );
		$reqRecord = ( new RequestRecords() )->loadReq( \substr( \wp_generate_uuid4(), 0, 10 ), $ipRecord->id );

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\Ops\Record $record */
		$record = $dbh->getRecord();
		$record->event_slug = $eventSlug;
		$record->site_id = $overrides[ 'site_id' ] ?? \get_current_blog_id();
		$record->req_ref = $reqRecord->id;

		$dbh->getQueryInserter()->insert( $record );

		global $wpdb;
		return (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
	}

	// ── Malware Records ───────────────────────────────────────────

	/**
	 * Insert a malware record using raw insert data (matching CreateLocalMalwareRecords pattern).
	 *
	 * @param string $filePath  Relative path fragment (relative to ABSPATH).
	 */
	public static function insertMalwareRecord( string $filePath, string $content = 'test-content', array $overrides = [] ) :int {
		$dbh = self::con()->db_con->malware;
		$dbh->getQueryInserter()->setInsertData( \array_merge( [
			'hash_sha256'  => \hash( 'sha256', $content, true ),
			'file_path'    => $filePath,
			'code_type'    => MalwareHandler::CODE_TYPE_PHP,
			'sig'          => \base64_encode( 'test_sig' ),
			'is_valid_file' => 0,
			'malai_status' => MalwareStatus::STATUS_UNKNOWN,
			'file_content' => \base64_encode( $content ),
			'last_seen_at' => Services::Request()->ts(),
		], $overrides ) )->query();

		global $wpdb;
		return (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
	}

	// Scan Result Fixtures

	/**
	 * Insert a completed scan record and return its ID.
	 */
	public static function insertCompletedScan( string $scanSlug, ?int $finishedAt = null ) :int {
		$dbh = self::con()->db_con->scans;
		$record = $dbh->getRecord();
		$record->scan = $scanSlug;
		$record->ready_at = \max( 1, ( $finishedAt ?? \time() ) - 60 );
		$record->finished_at = $finishedAt ?? \time();
		$dbh->getQueryInserter()->insert( $record );
		return (int)$dbh->getQuerySelector()->setOrderBy( 'id', 'DESC', true )->first()->id;
	}

	/**
	 * Insert a scan result item, link it to a scan, and add one meta flag.
	 */
	public static function insertScanResultMeta( int $scanId, string $metaKey ) :void {
		$resultItemsDb = self::con()->db_con->scan_result_items;
		$item = $resultItemsDb->getRecord();
		$item->item_type = 'f';
		$item->item_id = \uniqid( 'result-item-', true );
		$resultItemsDb->getQueryInserter()->insert( $item );
		$resultItemId = (int)$resultItemsDb->getQuerySelector()->setOrderBy( 'id', 'DESC', true )->first()->id;

		$scanResultsDb = self::con()->db_con->scan_results;
		$scanResult = $scanResultsDb->getRecord();
		$scanResult->scan_ref = $scanId;
		$scanResult->resultitem_ref = $resultItemId;
		$scanResultsDb->getQueryInserter()->insert( $scanResult );

		$metaDb = self::con()->db_con->scan_result_item_meta;
		$meta = $metaDb->getRecord();
		$meta->ri_ref = $resultItemId;
		$meta->meta_key = $metaKey;
		$meta->meta_value = 1;
		$metaDb->getQueryInserter()->insert( $meta );
	}

	// MFA Records

	/**
	 * Insert an MFA record for a user.
	 */
	public static function insertMfaRecord( int $userId, string $slug, array $data = [], array $overrides = [] ) :int {
		$con = self::con();
		$dbh = $con->db_con->mfa;

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops\Record $record */
		$record = $dbh->getRecord();
		$record->user_id = $userId;
		$record->slug = $slug;
		$record->unique_id = $overrides[ 'unique_id' ] ?? \wp_generate_uuid4();
		$record->label = $overrides[ 'label' ] ?? 'Test MFA';
		$record->data = $data;

		$dbh->getQueryInserter()->insert( $record );

		global $wpdb;
		return (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
	}
}
