<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Delivery;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\{
	FeedClient,
	FeedResponse,
	RuleFileVerifier
};

class FeedClientTest extends BaseUnitTest {

	/**
	 * @var array the response the stubbed HTTP call returns
	 */
	private $response;

	private array $requests = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_remote_get' )->alias( function ( string $url, array $args ) {
			$this->requests[] = [ $url, $args ];
			return $this->response;
		} );
		Functions\when( 'is_wp_error' )->alias( fn( $value ) :bool => $value instanceof \WP_Error );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( fn( array $response ) => $response[ 'response' ][ 'code' ] );
		Functions\when( 'wp_remote_retrieve_header' )->alias( fn( array $response, string $name ) => $response[ 'headers' ][ $name ] ?? '' );
		Functions\when( 'wp_remote_retrieve_body' )->alias( fn( array $response ) => $response[ 'body' ] );
	}

	public function testAsksForTheInstalledSchemaWithoutTokenQueryOrSiteName() :void {
		$this->response = self::answer( 200, 'file' );

		( new FeedClient( '22.3.0' ) )->fetch( 'plugin', 'example', 7 );

		[ $url, $args ] = $this->requests[ 0 ];
		$this->assertSame( 'https://api.orlesecurity.com/api/apto-wphashes/v2/virtual-patches/7/plugin/example', $url );
		$this->assertSame( [ 10, 0, 'Shield/22.3.0', RuleFileVerifier::MAX_BYTES + 1 ], [
			$args[ 'timeout' ], $args[ 'redirection' ], $args[ 'user-agent' ], $args[ 'limit_response_size' ],
		] );
	}

	public function testTellsEachAnswerApart() :void {
		$cases = [
			'file'                 => [ self::answer( 200, '{"format":"x"}' ), FeedResponse::FILE ],
			'no rules'             => [ self::answer( 404, '', 'none' ), FeedResponse::NONE ],
			'retired schema'       => [ self::answer( 404, '', 'schema-retired' ), FeedResponse::SCHEMA_RETIRED ],
			'unmarked not found'   => [ self::answer( 404, '' ), FeedResponse::UNCHANGED ],
			'not ready'            => [ self::answer( 503, '' ), FeedResponse::UNCHANGED ],
			'server error'         => [ self::answer( 500, '', 'none' ), FeedResponse::UNCHANGED ],
			'transport failure'    => [ new \WP_Error( 'http_request_failed', 'timeout' ), FeedResponse::TRANSPORT ],
		];
		foreach ( $cases as $label => [ $response, $type ] ) {
			$this->response = $response;
			$this->assertSame( $type, ( new FeedClient( '22.3.0' ) )->fetch( 'theme', 'child', 7 )->type, $label );
		}
		$this->response = self::answer( 200, '{"format":"x"}' );
		$this->assertSame( '{"format":"x"}', ( new FeedClient( '22.3.0' ) )->fetch( 'theme', 'child', 7 )->bytes );
	}

	private static function answer( int $code, string $body, string $marker = '' ) :array {
		return [
			'response' => [ 'code' => $code ],
			'headers'  => $marker === '' ? [] : [ FeedClient::MARKER_HEADER => $marker ],
			'body'     => $body,
		];
	}
}
