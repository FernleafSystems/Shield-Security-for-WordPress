<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Delivery;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\{
	Base64,
	RuleFileRefused,
	RuleFileVerifier,
	TrustedKeys
};
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The orchestrator's shared vectors are the executable form of the reader checks: each case names the file outcome
 * a Shield site must reach.
 */
class RuleFileVerifierTest extends TestCase {

	use DeliveryFixtures;

	public static function shieldCases() :array {
		$expected = \json_decode( (string)\file_get_contents( self::vectors().'expected.json' ), true );
		$cases = [];
		foreach ( $expected[ 'cases' ] as $case ) {
			if ( isset( $case[ 'shield' ] ) ) {
				$cases[ $case[ 'name' ] ] = [ $case + [ 'now' => $expected[ 'now' ] ] ];
			}
		}
		return $cases;
	}

	#[DataProvider( 'shieldCases' )]
	public function testReachesTheFileOutcomeTheSharedVectorsName( array $case ) :void {
		$keys = new TrustedKeys( \json_decode( (string)\file_get_contents( self::vectors().'keys.json' ), true ) );
		$this->assertSame( $case[ 'shield' ][ 'outcome' ], self::outcome( new RuleFileVerifier( $keys ), $case ) );
	}

	public function testVectorKeysAreTheFixedTestSeeds() :void {
		$keys = \json_decode( (string)\file_get_contents( self::vectors().'keys.json' ), true );
		$this->assertSame( [ self::testKey( 1 )[ 'keyId' ], self::testKey( 2 )[ 'keyId' ] ], \array_column( $keys, 'keyId' ) );
	}

	public function testTheProductionKeyIsWellFormed() :void {
		$this->assertSame( 1, ( new TrustedKeys() )->find( TrustedKeys::PRODUCTION[ 0 ][ 'keyId' ] )[ 'epoch' ] ?? null );
	}

	public function testAcceptsTheLargestFileTheOrchestratorWrites() :void {
		// The orchestrator writes envelopes up to 256 KiB.
		$rules = \array_map( fn( int $n ) :array => self::publishedRule( \sprintf( 'rule-%s-%06d', \str_repeat( 'x', 120 ), $n ) ), \range( 1, 220 ) );
		$bytes = self::ruleFile( 'plugin', 'example', 1, $rules );
		$this->assertGreaterThan( 200*1024, \strlen( $bytes ) );
		$file = ( new RuleFileVerifier( self::testKeys() ) )->verify( $bytes, 'plugin', 'example', 7, self::$nowMs );
		$this->assertCount( 220, $file->payload->rules );
	}

	public function testRefusesAListWhereTheOrchestratorWritesAnObject() :void {
		$this->expectExceptionObject( new RuleFileRefused( 'invalid_payload' ) );
		( new RuleFileVerifier( self::testKeys() ) )->verify(
			self::signed( '{"asset":[],"schema":7,"sequence":1,"issuedAt":"'.self::$issuedAt.'","rules":[]}' ),
			'plugin', 'example', 7, self::$nowMs
		);
	}

	public function testDecodesOnlyCanonicalPaddedBase64() :void {
		$this->assertSame( 'A', Base64::decode( 'QQ==' ) );
		foreach ( [ 'QQ', 'QR==', 'QQ-=', "QQ==\n" ] as $text ) {
			$this->assertNull( Base64::decode( $text ), $text );
		}
	}

	public function testRefusesAMalformedTrustedList() :void {
		$first = [ 'keyId' => self::testKey( 1 )[ 'keyId' ], 'epoch' => 1, 'publicKey' => \base64_encode( self::testKey( 1 )[ 'public' ] ) ];
		foreach ( [
			'key ID not from the key' => [ [ 'keyId' => '0000000000000000' ] + $first ],
			'reused epoch'            => [ $first, [ 'keyId' => self::testKey( 2 )[ 'keyId' ], 'epoch' => 1, 'publicKey' => \base64_encode( self::testKey( 2 )[ 'public' ] ) ] ],
		] as $label => $list ) {
			try {
				new TrustedKeys( $list );
				$this->fail( $label );
			}
			catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'Invalid trusted key.', $e->getMessage() );
			}
		}
	}

	/**
	 * A site's outcome for one vector file: refused by a file check, unchanged when it orders below the stored
	 * file, otherwise accepted. Rule-level outcomes are decided when the ruleset is built.
	 */
	private static function outcome( RuleFileVerifier $verifier, array $case ) :string {
		$now = (int)( new \DateTimeImmutable( $case[ 'now' ] ) )->format( 'Uv' );
		[ $kind, $slug ] = [ $case[ 'asset' ][ 'kind' ], $case[ 'asset' ][ 'slug' ] ];
		try {
			$file = $verifier->verify( (string)\file_get_contents( self::vectors().$case[ 'file' ] ), $kind, $slug, $case[ 'schema' ], $now );
			if ( isset( $case[ 'stored' ] ) ) {
				$stored = $verifier->verify( (string)\file_get_contents( self::vectors().$case[ 'stored' ] ), $kind, $slug, $case[ 'schema' ], $now );
				if ( $file->compareOrdering( $stored ) < 0 ) {
					return 'unchanged';
				}
			}
			return 'accepted';
		}
		catch ( RuleFileRefused $e ) {
			return 'refused';
		}
	}
}
