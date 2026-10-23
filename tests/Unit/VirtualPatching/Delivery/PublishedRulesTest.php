<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Delivery;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\{
	FeedState,
	PublishedRules,
	RuleFileStore,
	RuleFileVerifier,
	TrustedKeys
};
use FernleafSystems\Wordpress\Services\Core\Fs;
use PHPUnit\Framework\Attributes\DataProvider;

class PublishedRulesTest extends BaseUnitTest {

	use DeliveryFixtures;
	use TempDirLifecycleTrait;

	private const NOW = 1792756800;

	private RuleFileStore $store;

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
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public static function ruleCases() :array {
		$expected = \json_decode( (string)\file_get_contents( self::vectors().'expected.json' ), true );
		$cases = [];
		foreach ( $expected[ 'cases' ] as $case ) {
			if ( isset( $case[ 'shield' ][ 'rules' ] ) ) {
				$cases[ $case[ 'name' ] ] = [ $case ];
			}
		}
		return $cases;
	}

	#[DataProvider( 'ruleCases' )]
	public function testLoadsTheRulesTheSharedVectorsAcceptAndSkipsTheRest( array $case ) :void {
		$keys = new TrustedKeys( \json_decode( (string)\file_get_contents( self::vectors().'keys.json' ), true ) );
		$this->store->write( $case[ 'asset' ][ 'kind' ], $case[ 'asset' ][ 'slug' ], (string)\file_get_contents( self::vectors().$case[ 'file' ] ) );

		$loaded = ( new PublishedRules( $this->store, new RuleFileVerifier( $keys ), $keys, $case[ 'schema' ] ) )->load( new FeedState(), self::NOW );

		$this->assertSame( $case[ 'shield' ][ 'rules' ], \array_map( fn( array $rule ) :string => $rule[ 'rule' ][ 'id' ], $loaded[ 'rules' ] ) );
		$this->assertSame( $case[ 'shield' ][ 'skipped' ], \array_keys( $loaded[ 'rejected' ] ) );
	}

	public function testUsesAVerifiedFileWithoutCheckingItsSignatureAgain() :void {
		$file = self::ruleFile( 'plugin', 'example', 1, [ self::publishedRule() ] );
		$this->store->write( 'plugin', 'example', $file );
		$state = new FeedState();

		$loaded = $this->rules()->load( $state, self::NOW );
		$this->assertSame( [ [ 'plugin', 'example', 'log', 'candidate-1' ] ], self::summary( $loaded ) );
		$this->assertSame( \hash( 'sha256', $file ), $state->asset( 'plugin', 'example' )[ 'sha256' ] );

		$verifier = new class( self::testKeys() ) extends RuleFileVerifier {
			public function verify( string $bytes, string $kind, string $slug, int $schema, int $nowMs ) :\FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\VerifiedRuleFile {
				throw new \LogicException( 'verified again' );
			}
		};
		$again = ( new PublishedRules( $this->store, $verifier, self::testKeys(), 7 ) )->load( $state, self::NOW );
		$this->assertSame( self::summary( $loaded ), self::summary( $again ) );
	}

	public function testAStoredFileThatChangedOnDiskIsVerifiedAgainAndReported() :void {
		$this->store->write( 'plugin', 'example', self::ruleFile( 'plugin', 'example', 1, [ self::publishedRule() ] ) );
		$state = new FeedState();
		$this->rules()->load( $state, self::NOW );

		$changed = \json_decode( (string)$this->store->read( 'plugin', 'example' ), true );
		$changed[ 'payload' ] = \base64_encode( '{"asset":{"kind":"plugin","slug":"example"},"schema":7,"sequence":1,"issuedAt":"'.self::$issuedAt.'","rules":[]}' );
		$this->store->write( 'plugin', 'example', (string)\json_encode( $changed ) );

		$this->assertSame( [], $this->rules()->load( $state, self::NOW )[ 'rules' ] );
		$this->assertNull( $this->store->read( 'plugin', 'example' ) );
		$this->assertSame( [ 'plugin/example' => 'the stored file failed verification (invalid_signature)' ], $state->problems( self::NOW ) );
	}

	public function testDiscardsFilesOfARevokedKeyOrAnotherSchemaToFetchThemAgain() :void {
		$this->store->write( 'plugin', 'revoked', self::ruleFile( 'plugin', 'revoked', 1, [ self::publishedRule() ], 1 ) );
		$older = self::ruleFile( 'plugin', 'older', 1, [ self::publishedRule() ], 2, 6 );
		$this->store->write( 'plugin', 'older', $older );
		$state = new FeedState();
		$state->record( 'plugin', 'revoked', self::NOW, true, 'a', null );
		// Verified under the previous schema before an update: the recorded SHA-256 still matches.
		$state->record( 'plugin', 'older', self::NOW, true, \hash( 'sha256', $older ), null );

		$loaded = ( new PublishedRules( $this->store, new RuleFileVerifier( self::testKeys( [ 2 => 2 ] ) ), self::testKeys( [ 2 => 2 ] ), 7 ) )->load( $state, self::NOW );

		$this->assertSame( [], $loaded[ 'rules' ] );
		$this->assertSame( [], $this->store->assets() );
		$this->assertSame( [], $state->assets, 'both are asked about again as if new' );
		$this->assertSame( [], $state->problems( self::NOW ) );
	}

	public function testAnUnreadableStoredFileIsDiscardedAndReported() :void {
		$this->store->write( 'plugin', 'example', 'not json' );
		$state = new FeedState();

		$this->assertSame( [], $this->rules()->load( $state, self::NOW )[ 'rules' ] );
		$this->assertNull( $this->store->read( 'plugin', 'example' ) );
		$this->assertSame( [ 'plugin/example' => 'the stored file could not be read' ], $state->problems( self::NOW ) );
	}

	public function testEachRuleNeedsItsDisplayFieldsAndNotices() :void {
		$rules = [
			self::publishedRule( 'no-url', [ 'url' => '' ] ),
			self::publishedRule( 'no-notice', [ 'copyrights' => [] ] ),
			self::publishedRule( 'bad-notice', [ 'copyrights' => [ [ 'notice' => 'x', 'license' => '' , 'licenseUrl' => null ] ] ] ),
			self::publishedRule( 'no-licence-url', [ 'copyrights' => [ [ 'notice' => 'x', 'license' => 'y', 'licenseUrl' => null ] ] ] ),
			self::publishedRule( 'bad-licence-url', [ 'copyrights' => [ [ 'notice' => 'x', 'license' => 'y', 'licenseUrl' => 'javascript:alert(1)' ] ] ] ),
			self::publishedRule( 'blocking', [ 'mode' => 'block' ] ),
		];
		$this->store->write( 'plugin', 'example', self::ruleFile( 'plugin', 'example', 1, $rules ) );

		$loaded = $this->rules()->load( new FeedState(), self::NOW );

		$this->assertSame( [ [ 'plugin', 'example', 'log', 'no-licence-url' ], [ 'plugin', 'example', 'block', 'blocking' ] ], self::summary( $loaded ) );
		$this->assertSame( [ 'no-url', 'no-notice', 'bad-notice', 'bad-licence-url' ], \array_keys( $loaded[ 'rejected' ] ) );
	}

	private function rules() :PublishedRules {
		return new PublishedRules( $this->store, new RuleFileVerifier( self::testKeys() ), self::testKeys(), 7 );
	}

	private static function summary( array $loaded ) :array {
		return \array_map( fn( array $rule ) :array => [ $rule[ 'kind' ], $rule[ 'slug' ], $rule[ 'mode' ], $rule[ 'rule' ][ 'id' ] ], $loaded[ 'rules' ] );
	}
}
