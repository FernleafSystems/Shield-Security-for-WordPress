<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\{
	LoadLogs,
	LogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogsMeta\Ops\Record as MetaRecord;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class LoadLogsMetaAttachmentTest extends TestCase {

	private static function apply( array $records, array $metaRecords ) :void {
		$testable = new class extends LoadLogs {

			public static function testApply( array $records, array $metaRecords ) :void {
				static::applyMetaToRecords( $records, $metaRecords );
			}
		};
		$testable::testApply( $records, $metaRecords );
	}

	private static function makeLogRecord( int $id ) :LogRecord {
		return new LogRecord( [ 'id' => $id ] );
	}

	private static function makeMetaRecord( int $logRef, string $key, $value ) :MetaRecord {
		$meta = new MetaRecord();
		$meta->log_ref = $logRef;
		$meta->meta_key = $key;
		$meta->meta_value = $value;
		return $meta;
	}

	public function test_meta_attached_to_correct_records() :void {
		$records = [
			10 => self::makeLogRecord( 10 ),
			20 => self::makeLogRecord( 20 ),
		];

		self::apply( $records, [
			self::makeMetaRecord( 10, 'uid', '5' ),
			self::makeMetaRecord( 20, 'plugin', 'shield' ),
		] );

		$this->assertSame( [ 'uid' => '5' ], $records[ 10 ]->meta_data );
		$this->assertSame( [ 'plugin' => 'shield' ], $records[ 20 ]->meta_data );
	}

	public function test_multiple_meta_keys_on_single_record() :void {
		$records = [
			10 => self::makeLogRecord( 10 ),
		];

		self::apply( $records, [
			self::makeMetaRecord( 10, 'uid', '5' ),
			self::makeMetaRecord( 10, 'plugin', 'shield' ),
			self::makeMetaRecord( 10, 'name', 'Test User' ),
		] );

		$this->assertSame( [
			'uid'    => '5',
			'plugin' => 'shield',
			'name'   => 'Test User',
		], $records[ 10 ]->meta_data );
	}

	public function test_empty_meta_records_leaves_records_unchanged() :void {
		$records = [
			10 => self::makeLogRecord( 10 ),
		];

		self::apply( $records, [] );

		$this->assertNull( $records[ 10 ]->meta_data );
	}

	public function test_empty_log_records_handles_gracefully() :void {
		$records = [];
		self::apply( $records, [
			self::makeMetaRecord( 999, 'uid', '5' ),
		] );
		$this->assertEmpty( $records );
	}

	public function test_meta_for_nonexistent_log_id_is_ignored() :void {
		$records = [
			10 => self::makeLogRecord( 10 ),
		];

		self::apply( $records, [
			self::makeMetaRecord( 10, 'uid', '5' ),
			self::makeMetaRecord( 999, 'orphan', 'value' ),
		] );

		$this->assertSame( [ 'uid' => '5' ], $records[ 10 ]->meta_data );
		$this->assertArrayNotHasKey( 999, $records );
	}

	public function test_records_without_matching_meta_retain_null() :void {
		$records = [
			10 => self::makeLogRecord( 10 ),
			20 => self::makeLogRecord( 20 ),
		];

		self::apply( $records, [
			self::makeMetaRecord( 10, 'uid', '5' ),
		] );

		$this->assertSame( [ 'uid' => '5' ], $records[ 10 ]->meta_data );
		$this->assertNull( $records[ 20 ]->meta_data );
	}

	public function test_duplicate_meta_key_last_wins() :void {
		$records = [
			10 => self::makeLogRecord( 10 ),
		];

		self::apply( $records, [
			self::makeMetaRecord( 10, 'uid', 'first' ),
			self::makeMetaRecord( 10, 'uid', 'last' ),
		] );

		$this->assertSame( [ 'uid' => 'last' ], $records[ 10 ]->meta_data );
	}
}
