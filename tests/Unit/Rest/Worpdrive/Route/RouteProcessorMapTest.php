<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Route;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host\ShieldWorpdriveHost;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route\{
	DatabaseData,
	DatabaseSchema,
	FilesystemMap,
	FilesystemZip,
	RouteProcessorMap
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures\{
	WorpdriveTestDb,
	WorpdriveTestFilesystemService
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\WorpdriveUnitTestCase;
use FernleafSystems\WorpdriveClient\Host\WorpdriveRuntime;

class RouteProcessorMapTest extends WorpdriveUnitTestCase {

	private string $pluginRoot;

	protected function setUp() :void {
		parent::setUp();

		$this->pluginRoot = $this->tempDir( 'worpdrive-route-plugin' );
		$this->installController( $this->pluginRoot, $this->tempDir( 'worpdrive-route-cache' ) );
		ServicesState::mergeItems( [
			'service_wpdb' => new WorpdriveTestDb(),
			'service_wpfs' => new WorpdriveTestFilesystemService(),
		] );
	}

	/**
	 * @dataProvider invalidRequiredZipPayloadProvider
	 */
	public function test_filesystem_zip_route_rejects_invalid_required_paths_before_zip_creation( array $filePaths ) :void {
		$request = new \WP_REST_Request( [
			'file_paths' => $filePaths,
			'dir'        => ABSPATH,
			'uuid'       => 'route-invalid-zip',
			'time_limit' => 30,
		] );

		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( 'Invalid encoded WorpDrive payload.' );

		( ( new RouteProcessorMap() )->map()[ FilesystemZip::class ] )( $request );
	}

	public static function invalidRequiredZipPayloadProvider() :array {
		return [
			'invalid base64' => [ [ \base64_encode( 'index.php' ), 'not valid base64' ] ],
			'empty decoded'  => [ [ \base64_encode( '' ) ] ],
		];
	}

	public function test_route_processor_wrap_preserves_client_missing_file_message() :void {
		$message = 'Requested files missing for ZIP: "wp-content/*buddyboss-theme/404.php"';
		$method = new \ReflectionMethod( RouteProcessorMap::class, 'wrapProcessor' );
		$method->setAccessible( true );

		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( $message );

		$method->invoke(
			new RouteProcessorMap(),
			new \WP_REST_Request(),
			function () use ( $message ) :array {
				throw new \Exception( $message );
			}
		);
	}

	public function test_filesystem_map_request_type_map_still_builds_full_package_map() :void {
		$fixturePath = 'z-keep-route-map-'.\uniqid().'.txt';
		$this->writeFile( ABSPATH.$fixturePath, 'keep' );
		$uuid = 'route-map';

		$result = $this->runFilesystemMapRoute( $uuid, 'map', ABSPATH );

		$this->assertSame(
			'https://shield.test/plugin/tmp/archive-route-map/full_map_db.sqlite3',
			$result[ 'href' ]
		);
		$this->assertNotEmpty( $this->fetchMapRow( $uuid, 'full', $fixturePath ) );
	}

	public function test_filesystem_map_hashless_still_uses_empty_hash_algorithm() :void {
		$fixturePath = 'z-keep-route-hashless-'.\uniqid().'.txt';
		$this->writeFile( ABSPATH.$fixturePath, 'keep' );
		$uuid = 'route-hashless';

		$result = $this->runFilesystemMapRoute( $uuid, 'hashless', ABSPATH );

		$this->assertSame(
			'https://shield.test/plugin/tmp/archive-route-hashless/hashless_map_db.sqlite3',
			$result[ 'href' ]
		);
		$this->assertGreaterThanOrEqual( 1, $result[ 'map_count' ] );

		$row = $this->fetchMapRow( $uuid, 'hashless', $fixturePath );
		$this->assertNotEmpty( $row );

		$this->assertSame( '', $row[ 'hash' ] );
		$this->assertSame( '', $row[ 'hash_alt' ] );
	}

	public function test_filesystem_map_with_abspath_still_includes_parent_wp_config() :void {
		$parentWpConfig = $this->writeParentWpConfigForTest( 'parent map config' );
		$uuid = 'route-map-parent-wp-config';

		$this->runFilesystemMapRoute( $uuid, 'map', ABSPATH );

		$this->assertNotEmpty( $this->fetchMapRow( $uuid, 'full', 'wp-config.php' ) );
		$this->assertSame( 'parent map config', \file_get_contents( $parentWpConfig ) );
	}

	public function test_filesystem_zip_with_abspath_still_includes_parent_wp_config() :void {
		$this->writeParentWpConfigForTest( 'parent zip config' );
		$uuid = 'route-zip-parent-wp-config';
		$request = new \WP_REST_Request( [
			'file_paths' => [ \base64_encode( 'wp-config.php' ) ],
			'dir'        => ABSPATH,
			'uuid'       => $uuid,
			'time_limit' => 30,
		] );

		WorpdriveRuntime::withHost(
			new ShieldWorpdriveHost(),
			fn() => ( ( new RouteProcessorMap() )->map()[ FilesystemZip::class ] )( $request )
		);

		$this->assertSame( 'parent zip config', $this->zipEntryContents( $this->singleFilesZipArchiveFor( $uuid ), 'wp-config.php' ) );
	}

	public function test_database_schema_route_converts_package_exceptions_to_api_exceptions() :void {
		ServicesState::mergeItems( [
			'service_wpdb' => ( new WorpdriveTestDb() )->setTableStatus( [
				[
					'Engine' => 'InnoDB',
					'Rows'   => 1,
				],
			] ),
		] );
		$request = new \WP_REST_Request( [
			'dump_method' => 'direct',
			'uuid'        => 'route-schema-exception',
			'time_limit'  => 30,
		] );

		$this->expectException( ApiException::class );

		WorpdriveRuntime::withHost(
			new ShieldWorpdriveHost(),
			fn() => ( ( new RouteProcessorMap() )->map()[ DatabaseSchema::class ] )( $request )
		);
	}

	public function test_database_data_route_creates_valid_export_zip_under_current_client() :void {
		$db = ( new WorpdriveTestDb() )->setTableStatus( [
			[
				'Name'           => 'wp_options',
				'Engine'         => 'InnoDB',
				'Rows'           => 1,
				'Avg_row_length' => 128,
				'Data_length'    => 128,
				'Index_length'   => 64,
			],
		] );
		ServicesState::mergeItems( [
			'service_wpdb' => $db,
		] );
		$uuid = 'route-db-export';
		$request = new \WP_REST_Request( [
			'table_export_map' => [
				'wp_options' => [
					'offset'        => 0,
					'page'          => 1,
					'completed_at'  => 0,
					'exported_rows' => 0,
					'max_page_rows' => 10,
					'chunk_size'    => 2,
				],
			],
			'uuid'             => $uuid,
			'time_limit'       => 30,
		] );

		$result = WorpdriveRuntime::withHost(
			new ShieldWorpdriveHost(),
			fn() => ( ( new RouteProcessorMap() )->map()[ DatabaseData::class ] )( $request )
		);

		$this->assertStringStartsWith(
			'https://shield.test/plugin/tmp/archive-route-db-export/',
			$result[ 'href' ]
		);
		$this->assertStringEndsWith( '_zipped_db_exp.archive', $result[ 'href' ] );

		$this->assertArrayHasKey( 'table_export_map', $result );
		$this->assertArrayHasKey( 'wp_options', $result[ 'table_export_map' ] );
		$tableStatus = $result[ 'table_export_map' ][ 'wp_options' ];
		$this->assertSame( 1, $tableStatus[ 'offset' ] );
		$this->assertSame( 2, $tableStatus[ 'page' ] );
		$this->assertSame( 1, $tableStatus[ 'exported_rows' ] );
		$this->assertSame( 10, $tableStatus[ 'max_page_rows' ] );
		$this->assertSame( 2, $tableStatus[ 'chunk_size' ] );
		$this->assertGreaterThan( 0, $tableStatus[ 'completed_at' ] );

		$sql = $this->zipEntryContents( $this->singleDbExportArchiveFor( $uuid ), 'data_options_1.sql' );
		$this->assertStringContainsString( 'INSERT INTO `wp_options`', $sql );
		$this->assertStringContainsString( '`option_id`, `option_name`, `option_value`, `autoload`', $sql );
		$this->assertStringContainsString( "'siteurl'", $sql );
		$this->assertStringContainsString( "'https://shield.test'", $sql );
	}

	private function runFilesystemMapRoute( string $uuid, string $type, string $dir ) :array {
		$request = new \WP_REST_Request( [
			'type'            => $type,
			'dir'             => trailingslashit( $dir ),
			'file_exclusions' => [
				'contains' => [],
				'regex'    => [],
			],
			'newer_than_ts'   => 0,
			'uuid'            => $uuid,
			'time_limit'      => 30,
		] );

		return WorpdriveRuntime::withHost(
			new ShieldWorpdriveHost(),
			fn() => ( ( new RouteProcessorMap() )->map()[ FilesystemMap::class ] )( $request )
		);
	}

	private function fetchMapRow( string $uuid, string $type, string $relativePath ) :array {
		$db = new \SQLite3( \sprintf( '%s/tmp/archive-%s/%s_map_db.sqlite3', $this->pluginRoot, $uuid, $type ) );
		$result = $db->query( 'SELECT path, hash, hash_alt FROM file_item' );
		while ( $row = $result->fetchArray( \SQLITE3_ASSOC ) ) {
			$decodedPath = (string)\base64_decode( $row[ 'path' ] );
			if ( $decodedPath === $relativePath || \str_ends_with( $decodedPath, '/'.$relativePath ) ) {
				$db->close();
				return $row;
			}
		}
		$db->close();
		return [];
	}

	private function singleDbExportArchiveFor( string $uuid ) :string {
		$files = \glob( \sprintf( '%s/tmp/archive-%s/*_zipped_db_exp.archive', $this->pluginRoot, $uuid ) ) ?: [];
		$this->assertCount( 1, $files );
		return $files[ 0 ];
	}

	private function singleFilesZipArchiveFor( string $uuid ) :string {
		$files = \glob( \sprintf( '%s/tmp/archive-%s/*_zipped_files.archive', $this->pluginRoot, $uuid ) ) ?: [];
		$this->assertCount( 1, $files );
		return $files[ 0 ];
	}

	private function zipEntryContents( string $archivePath, string $entryName ) :string {
		$zip = new \ZipArchive();
		$this->assertTrue( $zip->open( $archivePath ) === true );
		$contents = $zip->getFromName( $entryName );
		$zip->close();

		$this->assertIsString( $contents );
		return $contents;
	}

	private function writeParentWpConfigForTest( string $contents ) :string {
		$parentWpConfig = $this->normalizePath( \dirname( wp_normalize_path( ABSPATH ) ).'/wp-config.php' );
		if ( \file_exists( $parentWpConfig ) ) {
			$this->markTestSkipped( 'Parent wp-config.php already exists in the unit test parent directory.' );
		}
		return $this->writeFile( $parentWpConfig, $contents );
	}
}
