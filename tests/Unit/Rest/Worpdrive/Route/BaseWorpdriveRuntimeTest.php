<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route\{
	BaseWorpdrive,
	Clean as CleanRoute,
	DatabaseData,
	FilesystemZip
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures\{
	WorpdriveTestDb,
	WorpdriveTestFilesystemService
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\WorpdriveUnitTestCase;

class BaseWorpdriveRuntimeTest extends WorpdriveUnitTestCase {

	protected function setUp() :void {
		parent::setUp();
		ServicesState::mergeItems( [
			'service_wpdb' => new WorpdriveTestDb(),
			'service_wpfs' => new WorpdriveTestFilesystemService(),
		] );
	}

	public function test_process_request_resets_runtime_host_after_success() :void {
		$response = $this->invokeProcessRequest(
			new CleanRoute( [ 'strict_parameters' => false ] ),
			new \WP_REST_Request( [
				'uuid'       => 'runtime-success',
				'time_limit' => 0,
			] )
		);

		$this->assertSame( 0, $response[ 'error_code' ] );
		$this->assertSame( [], $response[ 'status' ] );
		$this->assertRuntimeReset();
	}

	public function test_process_request_resets_runtime_host_after_api_exception() :void {
		$response = $this->invokeProcessRequest(
			new FilesystemZip( [ 'strict_parameters' => false ] ),
			new \WP_REST_Request( [
				'file_paths' => [ 'not valid base64' ],
				'dir'        => ABSPATH,
				'uuid'       => 'runtime-exception',
				'time_limit' => 30,
			] )
		);

		$this->assertSame( 1, $response[ 'error_code' ] );
		$this->assertSame( 500, $response[ 'http_status' ] );
		$this->assertSame( 'Invalid encoded WorpDrive payload.', $response[ 'message' ] );
		$this->assertRuntimeReset();
	}

	public function test_process_request_preserves_database_data_structured_error_status() :void {
		$response = $this->invokeProcessRequest(
			new DatabaseData( [ 'strict_parameters' => false ] ),
			new \WP_REST_Request( [
				'table_export_map' => [
					'wp_unknown' => [
						'offset'        => 0,
						'page'          => 0,
						'completed_at'  => 0,
						'exported_rows' => 0,
						'max_page_rows' => 10,
						'chunk_size'    => 2,
					],
				],
				'uuid'             => 'runtime-db-structured-error',
				'time_limit'       => 30,
			] )
		);

		$this->assertSame( 0, $response[ 'error_code' ] );
		$this->assertSame( '', $response[ 'status' ][ 'href' ] );
		$this->assertSame( [], $response[ 'status' ][ 'table_export_map' ] );
		$this->assertSame( 'db_export_invalid_map', $response[ 'status' ][ 'error' ][ 'code' ] );
		$this->assertSame( 'database_data', $response[ 'status' ][ 'error' ][ 'stage' ] );
		$this->assertTrue( $response[ 'status' ][ 'error' ][ 'retryable' ] );
		$this->assertIsArray( $response[ 'status' ][ 'error_context' ][ 'table_export_map' ] );
		$this->assertRuntimeReset();
	}

	/**
	 * @dataProvider validDirProvider
	 */
	public function test_dir_validation_accepts_exact_supported_roots( string $dir ) :void {
		$this->assertTrue( $this->invokeValidateRequestArg(
			new FilesystemZip( [ 'strict_parameters' => false ] ),
			$dir,
			'dir'
		) );
	}

	public static function validDirProvider() :array {
		return [
			'abspath'               => [ ABSPATH ],
			'abspath without slash' => [ \rtrim( ABSPATH, '/\\' ) ],
			'abspath backslashes'   => [ \str_replace( '/', '\\', ABSPATH ) ],
			'parent'                => [ \dirname( ABSPATH ) ],
			'parent with slash'     => [ \rtrim( \dirname( ABSPATH ), '/\\' ).'/' ],
		];
	}

	/**
	 * @dataProvider invalidDirProvider
	 */
	public function test_dir_validation_rejects_sibling_prefixes( string $dir ) :void {
		$this->assertInstanceOf(
			\WP_Error::class,
			$this->invokeValidateRequestArg(
				new FilesystemZip( [ 'strict_parameters' => false ] ),
				$dir,
				'dir'
			)
		);
	}

	public static function invalidDirProvider() :array {
		$abspathSibling = \rtrim( ABSPATH, '/\\' ).'2';
		$parentSibling = \dirname( ABSPATH ).'2';
		return [
			'abspath sibling prefix'             => [ $abspathSibling ],
			'abspath sibling prefix backslashes' => [ \str_replace( '/', '\\', $abspathSibling ) ],
			'parent sibling prefix'              => [ $parentSibling ],
			'child path under abspath'            => [ \rtrim( ABSPATH, '/\\' ).'/wp-content' ],
		];
	}

	private function invokeProcessRequest( BaseWorpdrive $route, \WP_REST_Request $request ) :array {
		$method = new \ReflectionMethod( BaseWorpdrive::class, 'processRequest' );
		$method->setAccessible( true );
		return $method->invoke( $route, $request );
	}

	/**
	 * @return bool|\WP_Error
	 */
	private function invokeValidateRequestArg( BaseWorpdrive $route, string $value, string $key ) {
		$method = new \ReflectionMethod( BaseWorpdrive::class, 'customValidateRequestArg' );
		$method->setAccessible( true );
		return $method->invoke( $route, $value, new \WP_REST_Request(), $key );
	}
}
