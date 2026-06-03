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

	private function invokeProcessRequest( BaseWorpdrive $route, \WP_REST_Request $request ) :array {
		$method = new \ReflectionMethod( BaseWorpdrive::class, 'processRequest' );
		$method->setAccessible( true );
		return $method->invoke( $route, $request );
	}
}
