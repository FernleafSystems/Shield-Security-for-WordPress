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
		Functions\when( 'esc_js' )->alias( static fn( $value ) :string => \addslashes( (string)$value ) );
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

	public function testCachedPostsAreCanonicalizedLimitedAndRepaired() :void {
		$general = $this->installEnvironment( [
			[ 'id' => 201, 'title' => 'Cached', 'excerpt' => 'Excerpt', 'href' => 'https://example.test/cached?utm_source=in-plugin' ],
			[ 'id' => 202, 'title' => [], 'excerpt' => new \stdClass(), 'href' => 'https://example.test/second' ],
			[ 'id' => 203, 'title' => 'Missing href', 'excerpt' => 'Excerpt' ],
			'not-an-array',
		], [] );

		$posts = ( new OurLatestBlogPosts() )->retrieve( 1 );

		$this->assertCount( 1, $posts );
		$this->assertSame( 201, $posts[ 0 ][ 'id' ] );
		$this->assertCount( 2, $general->lastStoredTransientValue );
		$this->assertSame( 'Unknown title', $general->lastStoredTransientValue[ 1 ][ 'title' ] );
		$this->assertSame( 'Excerpt', $general->lastStoredTransientValue[ 1 ][ 'excerpt' ] );
		$this->assertSame( 0, $general->http->callCount );
		$this->assertSame( \DAY_IN_SECONDS*2, $general->lastStoredTransientLifetime );
		$this->assertSame( 1, $general->setCount );
		$this->assertSame( 0, $general->deleteCount );
	}

	public function testFreshAndCachedPostsProduceEquivalentTrackingIdempotentOutput() :void {
		$freshGeneral = $this->installEnvironment( false, [
			[
				'type' => 'post',
				'id' => 301,
				'link' => 'https://example.test/post',
				'title' => [ 'rendered' => 'Title' ],
				'excerpt' => [ 'rendered' => "<p>O'Reilly</p>" ],
			],
		] );
		$fresh = ( new OurLatestBlogPosts() )->retrieve();
		$cachedGeneral = $this->installEnvironment( $freshGeneral->lastStoredTransientValue, [] );

		$cached = ( new OurLatestBlogPosts() )->retrieve();

		$this->assertSame( $fresh, $cached );
		$this->assertSame( "O\\'Reilly", $cached[ 0 ][ 'excerpt' ] );
		$this->assertSame( 1, \substr_count( $cached[ 0 ][ 'href' ], 'utm_source=' ) );
		$this->assertSame( 0, $cachedGeneral->http->callCount );
	}

	public function testSourceModesAcceptOnlyTheirOwnedUrlCarrier() :void {
		$freshGeneral = $this->installEnvironment( false, [
			[ 'type' => 'post', 'id' => 1, 'href' => 'https://example.test/wrong-fresh-carrier' ],
		] );
		$this->assertSame( [], ( new OurLatestBlogPosts() )->retrieve() );
		$this->assertSame( [], $freshGeneral->lastStoredTransientValue );

		$cachedGeneral = $this->installEnvironment( [
			[ 'id' => 2, 'link' => 'https://example.test/wrong-cache-carrier' ],
		], [
			[ 'type' => 'post', 'id' => 3, 'link' => 'https://example.test/recovered' ],
		] );
		$recovered = ( new OurLatestBlogPosts() )->retrieve();

		$this->assertSame( [ 3 ], \array_column( $recovered, 'id' ) );
		$this->assertSame( 1, $cachedGeneral->deleteCount );
		$this->assertSame( 1, $cachedGeneral->http->callCount );
	}

	public function testExactCanonicalCacheIsReturnedWithoutRewriteOrTrackingMutation() :void {
		$cached = [
			[
				'id'      => 'canonical-id',
				'title'   => 'Title',
				'excerpt' => 'Excerpt',
				'href'    => 'https://example.test/cached?utm_source=in-plugin',
			],
		];
		$general = $this->installEnvironment( $cached, [] );

		$this->assertSame( $cached, ( new OurLatestBlogPosts() )->retrieve() );
		$this->assertSame( 0, $general->setCount );
		$this->assertSame( 0, $general->deleteCount );
		$this->assertSame( 0, $general->http->callCount );
		$this->assertSame( 1, \substr_count( $cached[ 0 ][ 'href' ], 'utm_source=' ) );
	}

	/**
	 * @dataProvider validScalarIdProvider
	 */
	public function testFreshModePreservesEstablishedNonemptyScalarIds( $id ) :void {
		$general = $this->installEnvironment( false, [
			[ 'type' => 'post', 'id' => $id, 'link' => 'https://example.test/post' ],
		] );

		$posts = ( new OurLatestBlogPosts() )->retrieve();

		$this->assertSame( $id, $posts[ 0 ][ 'id' ] ?? null );
		$this->assertSame( $id, $general->lastStoredTransientValue[ 0 ][ 'id' ] ?? null );
	}

	public static function validScalarIdProvider() :array {
		return [
			'integer' => [ 1 ],
			'numeric string' => [ '1' ],
			'nonnumeric string' => [ 'post-id' ],
			'float' => [ 1.5 ],
			'boolean true' => [ true ],
		];
	}

	/**
	 * @dataProvider invalidIdProvider
	 */
	public function testFreshModeRejectsEmptyOrStructuredIds( $id ) :void {
		$general = $this->installEnvironment( false, [
			[ 'type' => 'post', 'id' => $id, 'link' => 'https://example.test/post' ],
		] );

		$this->assertSame( [], ( new OurLatestBlogPosts() )->retrieve() );
		$this->assertSame( [], $general->lastStoredTransientValue );
	}

	public static function invalidIdProvider() :array {
		return [
			'zero integer' => [ 0 ],
			'zero string' => [ '0' ],
			'empty string' => [ '' ],
			'boolean false' => [ false ],
			'null' => [ null ],
			'array' => [ [ 1 ] ],
			'object' => [ new \stdClass() ],
		];
	}

	/**
	 * @dataProvider limitProvider
	 */
	public function testFreshAndCachedModesApplyIdenticalArraySliceLimitSemantics(
		int $limit,
		array $expectedIds
	) :void {
		$raw = [
			[ 'type' => 'post', 'id' => 1, 'link' => 'https://example.test/one' ],
			[ 'type' => 'post', 'id' => 2, 'link' => 'https://example.test/two' ],
			[ 'type' => 'post', 'id' => 3, 'link' => 'https://example.test/three' ],
		];
		$freshGeneral = $this->installEnvironment( false, $raw );
		$fresh = ( new OurLatestBlogPosts() )->retrieve( $limit );
		$canonical = $freshGeneral->lastStoredTransientValue;
		$cachedGeneral = $this->installEnvironment( $canonical, [] );
		$cached = ( new OurLatestBlogPosts() )->retrieve( $limit );

		$this->assertSame( $expectedIds, \array_column( $fresh, 'id' ) );
		$this->assertSame( $fresh, $cached );
		$this->assertCount( 3, $canonical );
		$this->assertSame( 1, $freshGeneral->http->callCount );
		$this->assertSame( 1, $freshGeneral->setCount );
		$this->assertSame( 0, $cachedGeneral->http->callCount );
		$this->assertSame( 0, $cachedGeneral->setCount );
	}

	public static function limitProvider() :array {
		return [
			'zero' => [ 0, [] ],
			'one' => [ 1, [ 1 ] ],
			'larger than result' => [ 99, [ 1, 2, 3 ] ],
			'negative one' => [ -1, [ 1, 2 ] ],
			'negative larger than result' => [ -99, [] ],
		];
	}

	public function testFreshCacheStoresFullCanonicalResultBeforeApplyingLimit() :void {
		$general = $this->installEnvironment( false, [
			[ 'type' => 'post', 'id' => 1, 'link' => 'https://example.test/one' ],
			[ 'type' => 'post', 'id' => 2, 'link' => 'https://example.test/two' ],
			[ 'type' => 'post', 'id' => 3, 'link' => 'https://example.test/three' ],
		] );

		$first = ( new OurLatestBlogPosts() )->retrieve( 1 );

		$this->assertCount( 1, $first );
		$this->assertCount( 3, $general->lastStoredTransientValue );
		$cachedGeneral = $this->installEnvironment( $general->lastStoredTransientValue, [] );
		$this->assertCount( 2, ( new OurLatestBlogPosts() )->retrieve( 2 ) );
		$this->assertSame( 0, $cachedGeneral->http->callCount );
	}

	public function testWhollyInvalidNonemptyCacheIsDeletedAndRefetchedOnce() :void {
		$general = $this->installEnvironment( [ [ 'id' => 9, 'href' => [] ] ], [
			[ 'type' => 'post', 'id' => 401, 'link' => 'https://example.test/recovered' ],
		] );

		$posts = ( new OurLatestBlogPosts() )->retrieve();

		$this->assertSame( 401, $posts[ 0 ][ 'id' ] );
		$this->assertSame( 1, $general->deleteCount );
		$this->assertSame( 1, $general->http->callCount );
		$this->assertSame( 1, $general->setCount );
		$this->assertSame( 401, $general->lastStoredTransientValue[ 0 ][ 'id' ] );
	}

	public function testEmptyCachedArrayDoesNotRefetch() :void {
		$general = $this->installEnvironment( [], [
			[ 'type' => 'post', 'id' => 501, 'link' => 'https://example.test/not-fetched' ],
		] );

		$this->assertSame( [], ( new OurLatestBlogPosts() )->retrieve() );
		$this->assertSame( 0, $general->http->callCount );
		$this->assertSame( 0, $general->setCount );
		$this->assertSame( 0, $general->deleteCount );
	}

	public function testMalformedJsonIsFetchedOnceAndCachedAsCanonicalEmptyResult() :void {
		$general = $this->installEnvironment( false, [], '{"broken":' );

		$this->assertSame( [], ( new OurLatestBlogPosts() )->retrieve() );
		$this->assertSame( 1, $general->http->callCount );
		$this->assertSame( [], $general->lastStoredTransientValue );
		$this->assertSame( 1, $general->setCount );
		$this->assertSame( \DAY_IN_SECONDS*2, $general->lastStoredTransientLifetime );
		$this->assertSame(
			'https://getshieldsecurity.com/wp-json/wp/v2/posts?per_page=5',
			$general->http->lastUrl
		);
	}

	private function installEnvironment(
		$transientValue,
		array $httpPosts,
		?string $rawHttpBody = null
	) :OurLatestBlogPostsGeneralStub {
		$general = new OurLatestBlogPostsGeneralStub( $transientValue );
		$general->http = new OurLatestBlogPostsHttpRequestStub( $httpPosts, $rawHttpBody );
		ServicesState::installItems( [
			'service_wpgeneral'   => $general,
			'service_httprequest' => $general->http,
		] );
		return $general;
	}
}

class OurLatestBlogPostsGeneralStub extends General {

	private $transientValue;

	public $lastStoredTransientValue;

	public int $lastStoredTransientLifetime = 0;

	public int $deleteCount = 0;
	public int $setCount = 0;
	public string $lastTransientKey = '';
	public string $lastDeletedTransientKey = '';

	public OurLatestBlogPostsHttpRequestStub $http;

	public function __construct( $transientValue ) {
		$this->transientValue = $transientValue;
	}

	public function canUseTransients() :bool {
		return true;
	}

	public function getTransient( $sKey ) {
		$this->lastTransientKey = (string)$sKey;
		return $this->transientValue;
	}

	public function setTransient( $sKey, $mValue, $nExpire = 0 ) {
		++$this->setCount;
		$this->lastTransientKey = (string)$sKey;
		$this->lastStoredTransientValue = $mValue;
		$this->lastStoredTransientLifetime = $nExpire;
		return true;
	}

	public function deleteTransient( $key ) :bool {
		++$this->deleteCount;
		$this->lastDeletedTransientKey = (string)$key;
		$this->transientValue = false;
		return true;
	}
}

class OurLatestBlogPostsHttpRequestStub extends HttpRequest {

	private array $posts;
	private ?string $rawBody;

	public int $callCount = 0;
	public string $lastUrl = '';
	public array $lastArgs = [];

	public function __construct( array $posts, ?string $rawBody = null ) {
		$this->posts = $posts;
		$this->rawBody = $rawBody;
	}

	public function getContent( string $url, $args = [] ) :string {
		++$this->callCount;
		$this->lastUrl = $url;
		$this->lastArgs = \is_array( $args ) ? $args : [];
		return $this->rawBody ?? (string)\json_encode( $this->posts );
	}
}
