<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Traffic\Lib\Utility;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility\{
	RequestLogDisplayPathBuilder,
	RequestQueryRedactor
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RequestLogDisplayPathBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'apply_filters' )->alias( static fn( string $tag, $value ) => $value );
	}

	public function test_builds_display_path_with_redacted_sensitive_query_values() :void {
		$record = $this->record( '/wp-login.php', 'key=reset-secret&reauth=1&login=admin' );

		$parts = $this->parseDisplayPath( ( new RequestLogDisplayPathBuilder() )->build( $record ) );

		$this->assertSame( '/wp-login.php', $parts[ 'path' ] );
		$this->assertArrayHasKey( 'key', $parts[ 'query' ] );
		$this->assertArrayHasKey( 'reauth', $parts[ 'query' ] );
		$this->assertArrayHasKey( 'login', $parts[ 'query' ] );
		$this->assertSame( 'redacted', $parts[ 'query' ][ 'key' ] );
		$this->assertSame( '1', $parts[ 'query' ][ 'reauth' ] );
		$this->assertSame( 'admin', $parts[ 'query' ][ 'login' ] );
		$this->assertNotContains( 'reset-secret', $parts[ 'query' ] );
	}

	public function test_builds_path_without_query_when_no_query_exists() :void {
		$this->assertSame(
			'/wp-admin/',
			( new RequestLogDisplayPathBuilder() )->build( $this->record( '/wp-admin/', '' ) )
		);
	}

	public function test_builds_display_path_with_filter_added_sensitive_keys() :void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) {
				return $tag === RequestQueryRedactor::FILTER_SENSITIVE_QUERY_KEYS
					? \array_merge( (array)$value, [ 'customer_magic' ] )
					: $value;
			}
		);

		$parts = $this->parseDisplayPath(
			( new RequestLogDisplayPathBuilder() )->build( $this->record( '/callback', 'customer_magic=custom-secret&ok=1' ) )
		);

		$this->assertArrayHasKey( 'customer_magic', $parts[ 'query' ] );
		$this->assertArrayHasKey( 'ok', $parts[ 'query' ] );
		$this->assertSame( 'redacted', $parts[ 'query' ][ 'customer_magic' ] );
		$this->assertSame( '1', $parts[ 'query' ][ 'ok' ] );
		$this->assertNotContains( 'custom-secret', $parts[ 'query' ] );
	}

	private function record( string $path, string $query ) :LogRecord {
		$record = new LogRecord();
		$record->path = $path;
		$record->meta = [
			'query' => $query,
		];
		return $record;
	}

	/**
	 * @return array{path:string,query:array<string,string>}
	 */
	private function parseDisplayPath( string $displayPath ) :array {
		$query = [];
		\parse_str( (string)\parse_url( $displayPath, \PHP_URL_QUERY ), $query );

		return [
			'path'  => (string)\parse_url( $displayPath, \PHP_URL_PATH ),
			'query' => \array_map( 'strval', $query ),
		];
	}
}
