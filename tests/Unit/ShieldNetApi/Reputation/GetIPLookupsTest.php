<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ShieldNetApi\Reputation;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\GetIPInfo;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\GetIPReputation;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class GetIPLookupsTest extends BaseUnitTest {

	public function test_info_lookup_returns_success_response() :void {
		$response = [
			'error_code' => 0,
			'rdns'       => [
				'hostname' => 'example.test',
			],
		];

		$this->assertSame( $response, $this->apiWithResponse( GetIPInfo::class, $response )->retrieve() );
	}

	public function test_reputation_lookup_returns_success_response() :void {
		$response = [
			'error_code'       => 0,
			'reputation_score' => 41,
		];

		$this->assertSame( $response, $this->apiWithResponse( GetIPReputation::class, $response )->retrieve() );
	}

	/**
	 * @dataProvider invalidResponseProvider
	 */
	public function test_info_lookup_returns_empty_for_unsuccessful_response( ?array $response ) :void {
		$this->assertSame( [], $this->apiWithResponse( GetIPInfo::class, $response )->retrieve() );
	}

	/**
	 * @dataProvider invalidResponseProvider
	 */
	public function test_reputation_lookup_returns_empty_for_unsuccessful_response( ?array $response ) :void {
		$this->assertSame( [], $this->apiWithResponse( GetIPReputation::class, $response )->retrieve() );
	}

	public static function invalidResponseProvider() :array {
		return [
			'failed request'       => [ null ],
			'missing error_code'   => [ [ 'reputation_score' => 7 ] ],
			'non-zero error_code'  => [ [ 'error_code' => 5, 'message' => 'bad' ] ],
			'string error_code'    => [ [ 'error_code' => '0', 'reputation_score' => 7 ] ],
		];
	}

	private function apiWithResponse( string $class, ?array $response ) {
		if ( $class === GetIPInfo::class ) {
			return new class( $response ) extends GetIPInfo {
				private ?array $response;

				public function __construct( ?array $response ) {
					$this->response = $response;
				}

				protected function sendReq() :?array {
					return $this->response;
				}
			};
		}

		if ( $class === GetIPReputation::class ) {
			return new class( $response ) extends GetIPReputation {
				private ?array $response;

				public function __construct( ?array $response ) {
					$this->response = $response;
				}

				protected function sendReq() :?array {
					return $this->response;
				}
			};
		}

		throw new \RuntimeException( 'Unexpected IP lookup class: '.$class );
	}
}
