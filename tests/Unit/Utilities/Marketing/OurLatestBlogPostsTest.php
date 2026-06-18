<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\Marketing;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Marketing\OurLatestBlogPosts;
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Utilities\HttpRequest;

class OurLatestBlogPostsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();

		Functions\when( 'add_query_arg' )->alias(
			static fn( array $data, string $url ) :string => $url.'?'.\http_build_query( $data )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static fn( $value ) => \is_array( $value ) ? \array_map( '\rawurlencode', $value ) : \rawurlencode( (string)$value )
		);
		Functions\when( 'esc_js' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn( $value ) :string => \strip_tags( (string)$value )
		);
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testRetrieveIgnoresMalformedExternalPosts() :void {
		$general = $this->installEnvironment( false, [
			[ 'id' => 101, 'link' => 'https://example.test/missing-type' ],
			[ 'type' => 'page', 'id' => 102, 'link' => 'https://example.test/page' ],
			[ 'type' => 'post', 'id' => 104, 'link' => [] ],
			'not-a-post-array',
		] );

		$this->assertSame( [], ( new OurLatestBlogPosts() )->retrieve() );
		$this->assertSame( [], $general->lastStoredTransientValue );
		$this->assertSame( \DAY_IN_SECONDS*2, $general->lastStoredTransientLifetime );
	}

	public function testRetrieveKeepsValidPostsWhenExternalDataIsMixed() :void {
		$this->installEnvironment( false, [
			[ 'id' => 101, 'link' => 'https://example.test/missing-type' ],
			[
				'type'    => 'post',
				'id'      => 103,
				'link'    => 'https://example.test/post',
				'title'   => [ 'rendered' => 'Useful Post' ],
				'excerpt' => [ 'rendered' => '<p>Readable excerpt</p>' ],
			],
		] );

		$posts = ( new OurLatestBlogPosts() )->retrieve();

		$this->assertCount( 1, $posts );
		$this->assertSame( 103, $posts[ 0 ][ 'id' ] );
		$this->assertSame( 'Useful Post', $posts[ 0 ][ 'title' ] );
		$this->assertSame( 'Readable excerpt', $posts[ 0 ][ 'excerpt' ] );
		$this->assertStringStartsWith( 'https://example.test/post?', $posts[ 0 ][ 'href' ] );
	}

	public function testRetrieveDefaultsMalformedOptionalTextFields() :void {
		$this->installEnvironment( false, [
			[
				'type'    => 'post',
				'id'      => 105,
				'link'    => 'https://example.test/post',
				'title'   => [ 'rendered' => [] ],
				'excerpt' => [ 'rendered' => (object)[] ],
			],
		] );

		$posts = ( new OurLatestBlogPosts() )->retrieve();

		$this->assertCount( 1, $posts );
		$this->assertSame( 'Unknown title', $posts[ 0 ][ 'title' ] );
		$this->assertSame( 'Excerpt', $posts[ 0 ][ 'excerpt' ] );
	}

	private function installEnvironment( $transientValue, array $httpPosts ) :OurLatestBlogPostsGeneralStub {
		$general = new OurLatestBlogPostsGeneralStub( $transientValue );
		ServicesState::installItems( [
			'service_wpgeneral'   => $general,
			'service_httprequest' => new OurLatestBlogPostsHttpRequestStub( $httpPosts ),
		] );
		return $general;
	}
}

class OurLatestBlogPostsGeneralStub extends General {

	private $transientValue;

	public $lastStoredTransientValue;

	public int $lastStoredTransientLifetime = 0;

	public function __construct( $transientValue ) {
		$this->transientValue = $transientValue;
	}

	public function canUseTransients() :bool {
		return true;
	}

	public function getTransient( $sKey ) {
		return $this->transientValue;
	}

	public function setTransient( $sKey, $mValue, $nExpire = 0 ) {
		$this->lastStoredTransientValue = $mValue;
		$this->lastStoredTransientLifetime = $nExpire;
		return true;
	}
}

class OurLatestBlogPostsHttpRequestStub extends HttpRequest {

	private array $posts;

	public function __construct( array $posts ) {
		$this->posts = $posts;
	}

	public function getContent( string $url, $args = [] ) :string {
		return (string)\json_encode( $this->posts );
	}
}
