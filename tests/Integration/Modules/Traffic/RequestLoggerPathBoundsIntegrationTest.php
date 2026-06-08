<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops as ReqLogsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers\LocalDbWriter as TrafficLocalDbWriter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class RequestLoggerPathBoundsIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
	}

	public function test_overlong_path_is_bounded_for_full_request_log_insert() :void {
		$result = $this->writeRequestLogViaWriter( $this->overlongJsonLikePath(), false );
		$record = $this->requestLogRecord( $result[ 'record_id' ] );
		$storedPath = $record->path;

		$this->assertBoundedTruncatedPath( $storedPath );
		$this->assertStringStartsWith(
			'/blog/wordpress-waf/%7B%22uploadedSrc%22:%22https:/assets.getshieldsecurity.com/',
			$storedPath
		);
		$this->assertNoTruncationMetadata( $this->decodedRecordMeta( $record ) );
	}

	public function test_overlong_path_is_bounded_for_dependent_request_log_update() :void {
		$result = $this->writeRequestLogViaWriter( $this->overlongJsonLikePath(), true );
		$this->assertSame( $result[ 'precreated_id' ], $result[ 'record_id' ] );

		$record = $this->requestLogRecord( $result[ 'record_id' ] );

		$this->assertBoundedTruncatedPath( $record->path );
		$this->assertNoTruncationMetadata( $this->decodedRecordMeta( $record ) );
	}

	public function test_invalid_utf8_path_is_scrubbed_before_storage() :void {
		$result = $this->writeRequestLogViaWriter( '/invalid/'."\xC3\x28".\str_repeat( 'a', 600 ), false );
		$storedPath = $this->requestLogRecord( $result[ 'record_id' ] )->path;

		$this->assertNotSame( '', $storedPath );
		$this->assertLessThanOrEqual( $this->requestLogPathStorageLimit(), \mb_strlen( $storedPath, 'UTF-8' ) );
	}

	/**
	 * @return array{record_id:int, precreated_id:int|null}
	 */
	private function writeRequestLogViaWriter( string $path, bool $isDependent ) :array {
		$ip = '203.0.113.'.\wp_rand( 10, 250 );
		$ipRecord = ( new IPRecords() )->loadIP( $ip );
		$rid = \substr( \wp_generate_uuid4(), 0, 10 );
		$precreatedRecord = $isDependent ? ( new RequestRecords() )->loadReq( $rid, $ipRecord->id ) : null;

		$writer = new class() extends TrafficLocalDbWriter {
			public function writePrimaryForTest( array $record ) :ReqLogsDB\Record {
				return $this->createPrimaryLogRecord( $record );
			}
		};
		$writer->setRequestLogger( $this->requireController()->comps->requests_log );

		$writtenRecord = $this->withRequestLoggerDependentState(
			$isDependent,
			fn() => $writer->writePrimaryForTest( [
				'extra' => [
					'meta_shield'  => [
						'offense' => 0,
					],
					'meta_request' => [
						'ip'         => $ip,
						'rid'        => $rid,
						'verb'       => 'GET',
						'code'       => 404,
						'path'       => $path,
						'type'       => ReqLogsHandler::TYPE_HTTP,
						'has_params' => 1,
					],
					'meta_user'    => [
						'uid' => 0,
					],
					'meta_wp'      => [],
				],
			] )
		);

		return [
			'record_id'     => (int)$writtenRecord->id,
			'precreated_id' => $precreatedRecord instanceof ReqLogsDB\Record ? (int)$precreatedRecord->id : null,
		];
	}

	private function withRequestLoggerDependentState( bool $isDependent, callable $callback ) {
		$logger = $this->requireController()->comps->requests_log;
		$property = new \ReflectionProperty( $logger, 'isDependentLog' );
		$property->setAccessible( true );
		$snapshot = (bool)$property->getValue( $logger );
		$property->setValue( $logger, $isDependent );

		try {
			return $callback();
		}
		finally {
			$property->setValue( $logger, $snapshot );
		}
	}

	private function requestLogRecord( int $id ) :ReqLogsDB\Record {
		$record = $this->requireController()->db_con->req_logs->getQuerySelector()->byId( $id );
		$this->assertInstanceOf( ReqLogsDB\Record::class, $record );
		return $record;
	}

	private function assertBoundedTruncatedPath( string $path ) :void {
		$this->assertSame( $this->requestLogPathStorageLimit(), \mb_strlen( $path, 'UTF-8' ) );
		$this->assertStringEndsWith( '...', $path );
	}

	private function requestLogPathStorageLimit() :int {
		$limit = (int)( $this->requireController()->db_con->req_logs->getTableSchema()->getColumnDef( 'path' )[ 'length' ] ?? 0 );
		$this->assertSame( 512, $limit );
		return $limit;
	}

	private function overlongJsonLikePath() :string {
		$multiByteChar = (string)\json_decode( '"\u00e9"' );
		return '/blog/wordpress-waf/%7B%22uploadedSrc%22:%22https:/assets.getshieldsecurity.com/getshieldsecurity.com/uploads/2024/07/The-WordPress-WAF-blocking-options-in-Shield-Security-PRO.png%22,%22figureClassNames%22:%22wp-block-imagesize-large%22,%22caption%22:%22'
			   .\str_repeat( $multiByteChar, 600 );
	}

	private function decodedRecordMeta( ReqLogsDB\Record $record ) :array {
		$raw = $record->getRawData();
		$this->assertArrayHasKey( 'meta', $raw );
		return $this->decodedMeta( (string)$raw[ 'meta' ] );
	}

	private function decodedMeta( string $encoded ) :array {
		$decoded = \json_decode( (string)\base64_decode( $encoded ), true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	private function assertNoTruncationMetadata( array $meta ) :void {
		foreach ( \array_keys( $meta ) as $key ) {
			$this->assertStringNotContainsString( 'trunc', \strtolower( (string)$key ) );
		}
	}
}
