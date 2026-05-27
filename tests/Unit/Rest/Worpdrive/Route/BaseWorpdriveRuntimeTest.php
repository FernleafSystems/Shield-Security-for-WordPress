<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route\{
	BaseWorpdrive,
	Clean as CleanRoute,
	FilesystemZip
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures\WorpdriveTestFilesystemService;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\WorpdriveUnitTestCase;

class BaseWorpdriveRuntimeTest extends WorpdriveUnitTestCase {

	protected function setUp() :void {
		parent::setUp();
		ServicesState::mergeItems( [
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

	private function invokeProcessRequest( BaseWorpdrive $route, \WP_REST_Request $request ) :array {
		$method = new \ReflectionMethod( BaseWorpdrive::class, 'processRequest' );
		$method->setAccessible( true );
		return $method->invoke( $route, $request );
	}
}
