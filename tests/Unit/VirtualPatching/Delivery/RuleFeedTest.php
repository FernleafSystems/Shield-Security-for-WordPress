<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Delivery;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\{
	FeedClient,
	FeedResponse,
	FeedState,
	RuleFeed,
	RuleFileStore,
	RuleFileVerifier
};
use FernleafSystems\Wordpress\Services\Core\Fs;

class RuleFeedTest extends BaseUnitTest {

	use DeliveryFixtures;
	use TempDirLifecycleTrait;

	private const NOW = 1792756800;

	private RuleFileStore $store;

	private FeedState $state;

	/**
	 * @var array<string, FeedResponse> by "kind/slug"
	 */
	private array $responses = [];

	/**
	 * @var string[] "kind/slug" in the order asked
	 */
	private array $asked = [];

	private float $clock = 0;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpfs' => new class extends Fs {
				public function fs() {
					return null;
				}

				public function mkdir( $path ) {
					return \is_dir( $path ) || \mkdir( $path, 0755, true );
				}
			},
		] );
		$this->store = new RuleFileStore( $this->createTrackedTempDir().'/rules' );
		$this->state = new FeedState();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testStoresAVerifiedFileAndRecordsItsSha() :void {
		$file = self::ruleFile( 'plugin', 'example', 3, [ self::publishedRule() ] );
		$this->responses[ 'plugin/example' ] = new FeedResponse( FeedResponse::FILE, $file );

		$this->fetch( [ 'plugin/example' ] );

		$this->assertSame( $file, $this->store->read( 'plugin', 'example' ) );
		$this->assertSame( [ self::NOW, \hash( 'sha256', $file ), null ], [
			$this->state->asset( 'plugin', 'example' )[ 'refreshed_at' ],
			$this->state->asset( 'plugin', 'example' )[ 'sha256' ],
			$this->state->asset( 'plugin', 'example' )[ 'error' ],
		] );
	}

	public function testRefusesABadFileAndKeepsTheStoredOne() :void {
		$stored = self::ruleFile( 'plugin', 'example', 3 );
		$this->responses[ 'plugin/example' ] = new FeedResponse( FeedResponse::FILE, $stored );
		$this->fetch( [ 'plugin/example' ] );

		$this->responses[ 'plugin/example' ] = new FeedResponse( FeedResponse::FILE, self::ruleFile( 'plugin', 'example', 4, [], 3 ) );
		$this->fetch( [ 'plugin/example' ] );

		$this->assertSame( $stored, $this->store->read( 'plugin', 'example' ) );
		$this->assertSame( 'the delivered file was refused (untrusted_key)', $this->state->asset( 'plugin', 'example' )[ 'error' ] );
		$this->assertSame( [ 'plugin/example' => 'the delivered file was refused (untrusted_key)' ], $this->state->problems( self::NOW ) );
	}

	public function testKeepsTheStoredFileOverOneOrderingBelowItWithoutCountingARefresh() :void {
		$stored = self::ruleFile( 'plugin', 'example', 5 );
		$this->responses[ 'plugin/example' ] = new FeedResponse( FeedResponse::FILE, $stored );
		$this->fetch( [ 'plugin/example' ] );

		$this->responses[ 'plugin/example' ] = new FeedResponse( FeedResponse::FILE, self::ruleFile( 'plugin', 'example', 4 ) );
		$this->fetch( [ 'plugin/example' ], self::NOW + FeedState::STALE_AFTER + 1 );

		$this->assertSame( $stored, $this->store->read( 'plugin', 'example' ) );
		$this->assertSame( [ 'plugin/example' => 'no successful update for 3 days' ], $this->state->problems( self::NOW + FeedState::STALE_AFTER + 1 ) );
	}

	public function testANotFoundAnswerNeverRemovesAStoredFile() :void {
		$stored = self::ruleFile( 'plugin', 'example', 5 );
		$this->responses[ 'plugin/example' ] = new FeedResponse( FeedResponse::FILE, $stored );
		$this->fetch( [ 'plugin/example' ] );

		foreach ( [ FeedResponse::NONE, FeedResponse::UNCHANGED ] as $type ) {
			$this->responses[ 'plugin/example' ] = new FeedResponse( $type );
			$this->fetch( [ 'plugin/example' ], self::NOW + 60 );
			$this->assertSame( $stored, $this->store->read( 'plugin', 'example' ), $type );
			// A published slug always keeps a file, so neither answer counts as a refresh.
			$this->assertSame( self::NOW, $this->state->asset( 'plugin', 'example' )[ 'refreshed_at' ], $type );
		}
	}

	public function testAsksAboutStoredFilesFirstThenNewThenTheLongestUnasked() :void {
		$this->responses[ 'plugin/stored' ] = new FeedResponse( FeedResponse::FILE, self::ruleFile( 'plugin', 'stored', 1 ) );
		$this->fetch( [ 'plugin/stored' ] );
		$this->state->record( 'plugin', 'older', self::NOW - 200, true, null, null );
		$this->state->record( 'plugin', 'recent', self::NOW - 100, true, null, null );
		$this->asked = [];

		$this->fetch( [ 'plugin/recent', 'plugin/new', 'plugin/older', 'plugin/stored', 'plugin/Not Valid' ] );

		$this->assertSame( [ 'plugin/stored', 'plugin/new', 'plugin/older', 'plugin/recent' ], $this->asked );
	}

	public function testStopsAtTheFirstTransportFailureAndAtTheBudget() :void {
		$this->responses[ 'plugin/b' ] = new FeedResponse( FeedResponse::TRANSPORT );
		$this->fetch( [ 'plugin/a', 'plugin/b', 'plugin/c' ] );
		$this->assertSame( [ 'plugin/a', 'plugin/b' ], $this->asked );

		$this->asked = [];
		$this->responses = [];
		$this->state = new FeedState();
		$feed = $this->feed( function () :float {
			return $this->clock += RuleFeed::BUDGET_SECONDS/2;
		} );
		$feed->fetch( $this->assets( [ 'plugin/a', 'plugin/b', 'plugin/c' ] ), $this->state, self::NOW );
		$this->assertSame( [ 'plugin/a' ], $this->asked );
	}

	public function testKeepsTheSharedVectorsStoredFileOverTheLowerOrderingOne() :void {
		$keys = new \FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\TrustedKeys( \json_decode( (string)\file_get_contents( self::vectors().'keys.json' ), true ) );
		$stored = (string)\file_get_contents( self::vectors().'asset.json' );
		$this->responses[ 'plugin/example-plugin' ] = new FeedResponse( FeedResponse::FILE, $stored );
		$this->feed( null, $keys )->fetch( $this->assets( [ 'plugin/example-plugin' ] ), $this->state, self::NOW );

		$this->responses[ 'plugin/example-plugin' ] = new FeedResponse( FeedResponse::FILE, (string)\file_get_contents( self::vectors().'kept/lower-ordering.json' ) );
		$this->feed( null, $keys )->fetch( $this->assets( [ 'plugin/example-plugin' ] ), $this->state, self::NOW + 60 );

		$this->assertSame( $stored, $this->store->read( 'plugin', 'example-plugin' ) );
		$this->assertSame( self::NOW, $this->state->asset( 'plugin', 'example-plugin' )[ 'refreshed_at' ] );
		$this->assertSame( [], $this->state->problems( self::NOW + 60 ) );
	}

	public function testAnApiThatNeverAnswersIsReportedAfterThreeDays() :void {
		$this->fetch( [ 'plugin/a', 'plugin/b' ] );
		$this->responses[ 'plugin/a' ] = new FeedResponse( FeedResponse::TRANSPORT );
		$this->fetch( [ 'plugin/a', 'plugin/b' ], self::NOW + 3600 );
		$later = self::NOW + 3600 + FeedState::STALE_AFTER;
		$this->assertSame( [], $this->state->problems( $later ), 'neither asset is reported separately during the outage' );
		$this->assertSame( [ 'service' ], \array_keys( $this->state->problems( $later + 1 ) ) );

		$this->asked = [];
		$this->responses[ 'plugin/a' ] = new FeedResponse( FeedResponse::NONE );
		$this->fetch( [ 'plugin/a', 'plugin/b' ], self::NOW + 60 );
		$this->assertSame( [ 'plugin/b', 'plugin/a' ], $this->asked, 'the failing asset waits its turn' );
		$this->assertNull( $this->state->unreachableSince, 'any answer clears it' );
	}

	public function testARetiredSchemaIsRecordedUntilTheApiServesItAgain() :void {
		$this->responses[ 'plugin/a' ] = new FeedResponse( FeedResponse::SCHEMA_RETIRED );
		$this->fetch( [ 'plugin/a', 'plugin/b' ] );
		$this->assertSame( [ 'plugin/a' ], $this->asked );
		$this->assertArrayHasKey( 'schema', $this->state->problems( self::NOW ) );

		$this->responses[ 'plugin/a' ] = new FeedResponse( FeedResponse::NONE );
		$this->fetch( [ 'plugin/a' ] );
		$this->assertNull( $this->state->schemaRetiredAt );
	}

	/**
	 * @param string[] $keys
	 */
	private function fetch( array $keys, int $now = self::NOW ) :void {
		$this->feed()->fetch( $this->assets( $keys ), $this->state, $now );
	}

	private function feed( ?\Closure $clock = null, ?\FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\TrustedKeys $keys = null ) :RuleFeed {
		$client = new class( $this ) extends FeedClient {
			private RuleFeedTest $test;

			public function __construct( RuleFeedTest $test ) {
				parent::__construct( '22.3.0' );
				$this->test = $test;
			}

			public function fetch( string $kind, string $slug, int $schema ) :FeedResponse {
				return $this->test->answer( $kind.'/'.$slug );
			}
		};
		return new RuleFeed( $client, $this->store, new RuleFileVerifier( $keys ?? self::testKeys() ), 7, $clock ?? fn() :float => 0.0 );
	}

	/**
	 * @internal for the fake client
	 */
	public function answer( string $key ) :FeedResponse {
		$this->asked[] = $key;
		return $this->responses[ $key ] ?? new FeedResponse( FeedResponse::NONE );
	}

	/**
	 * @param string[] $keys
	 */
	private function assets( array $keys ) :array {
		$assets = [];
		foreach ( $keys as $key ) {
			[ $kind, $slug ] = \explode( '/', $key, 2 );
			$assets[ $key ] = [ 'kind' => $kind, 'slug' => $slug ];
		}
		return $assets;
	}
}
