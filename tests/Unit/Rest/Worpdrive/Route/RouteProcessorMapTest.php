<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Route;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host\ShieldWorpdriveHost;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route\{
	FilesystemMap,
	FilesystemZip,
	RouteProcessorMap
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures\WorpdriveTestFilesystemService;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\WorpdriveUnitTestCase;
use FernleafSystems\WorpdriveClient\Host\WorpdriveRuntime;

class RouteProcessorMapTest extends WorpdriveUnitTestCase {

	private string $pluginRoot;

	protected function setUp() :void {
		parent::setUp();

		$this->pluginRoot = $this->tempDir( 'worpdrive-route-plugin' );
		$this->installController( $this->pluginRoot, $this->tempDir( 'worpdrive-route-cache' ) );
		ServicesState::mergeItems( [
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

	public function invalidRequiredZipPayloadProvider() :array {
		return [
			'invalid base64' => [ [ \base64_encode( 'index.php' ), 'not valid base64' ] ],
			'empty decoded'  => [ [ \base64_encode( '' ) ] ],
		];
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
}
