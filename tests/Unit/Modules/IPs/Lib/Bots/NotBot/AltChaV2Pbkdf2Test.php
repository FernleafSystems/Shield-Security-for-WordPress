<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\AltChaV2Pbkdf2;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AltChaV2Pbkdf2Test extends BaseUnitTest {

	private const SIGNATURE_SECRET = 'test-signature-secret';
	private const NONCE = '000102030405060708090a0b0c0d0e0f';
	private const SALT = '101112131415161718191a1b1c1d1e1f';

	public function test_valid_deterministic_solution_verifies() :void {
		$subject = new AltChaV2Pbkdf2();
		$challenge = $this->buildChallenge( $subject, 7, 2000000000 );
		$solution = $this->buildSolution( $subject, $challenge, 7 );

		$this->assertTrue( $subject->verifySolution(
			$subject->encodeChallenge( $challenge ),
			\json_encode( $solution, \JSON_THROW_ON_ERROR ),
			self::SIGNATURE_SECRET,
			$subject->keySignatureSecret( self::SIGNATURE_SECRET ),
			1999999999
		) );
	}

	public function test_canonical_json_is_stable_for_unordered_parameters() :void {
		$subject = new AltChaV2Pbkdf2();

		$this->assertSame(
			$subject->canonicalJson( [
				'z' => 1,
				'a' => [
					'b' => 2,
					'a' => 1,
				],
			] ),
			$subject->canonicalJson( [
				'a' => [
					'a' => 1,
					'b' => 2,
				],
				'z' => 1,
			] )
		);
	}

	public function test_tampered_challenge_signature_is_rejected() :void {
		$subject = new AltChaV2Pbkdf2();
		$challenge = $this->buildChallenge( $subject, 7, 2000000000 );
		$solution = $this->buildSolution( $subject, $challenge, 7 );
		$challenge[ 'parameters' ][ 'cost' ] = 3;

		$this->expectException( \Exception::class );
		$subject->verifySolution(
			$subject->encodeChallenge( $challenge ),
			\json_encode( $solution, \JSON_THROW_ON_ERROR ),
			self::SIGNATURE_SECRET,
			$subject->keySignatureSecret( self::SIGNATURE_SECRET ),
			1999999999
		);
	}

	public function test_expired_challenge_is_rejected() :void {
		$subject = new AltChaV2Pbkdf2();
		$challenge = $this->buildChallenge( $subject, 7, 100 );
		$solution = $this->buildSolution( $subject, $challenge, 7 );

		$this->expectException( \Exception::class );
		$subject->verifySolution(
			$subject->encodeChallenge( $challenge ),
			\json_encode( $solution, \JSON_THROW_ON_ERROR ),
			self::SIGNATURE_SECRET,
			$subject->keySignatureSecret( self::SIGNATURE_SECRET ),
			101
		);
	}

	public function test_challenge_expires_at_exact_current_time_is_rejected() :void {
		$subject = new AltChaV2Pbkdf2();
		$challenge = $this->buildChallenge( $subject, 7, 100 );
		$solution = $this->buildSolution( $subject, $challenge, 7 );

		$this->expectException( \Exception::class );
		$subject->verifySolution(
			$subject->encodeChallenge( $challenge ),
			\json_encode( $solution, \JSON_THROW_ON_ERROR ),
			self::SIGNATURE_SECRET,
			$subject->keySignatureSecret( self::SIGNATURE_SECRET ),
			100
		);
	}

	public function test_wrong_solution_key_is_rejected() :void {
		$subject = new AltChaV2Pbkdf2();
		$challenge = $this->buildChallenge( $subject, 7, 2000000000 );
		$solution = $this->buildSolution( $subject, $challenge, 8 );

		$this->expectException( \Exception::class );
		$subject->verifySolution(
			$subject->encodeChallenge( $challenge ),
			\json_encode( $solution, \JSON_THROW_ON_ERROR ),
			self::SIGNATURE_SECRET,
			$subject->keySignatureSecret( self::SIGNATURE_SECRET ),
			1999999999
		);
	}

	/**
	 * @dataProvider provideMalformedSolutions
	 */
	public function test_malformed_solution_shape_is_rejected( array $solution ) :void {
		$subject = new AltChaV2Pbkdf2();
		$challenge = $this->buildChallenge( $subject, 7, 2000000000 );

		$this->expectException( \Exception::class );
		$subject->verifySolution(
			$subject->encodeChallenge( $challenge ),
			\json_encode( $solution, \JSON_THROW_ON_ERROR ),
			self::SIGNATURE_SECRET,
			$subject->keySignatureSecret( self::SIGNATURE_SECRET ),
			1999999999
		);
	}

	public function test_v1_payload_shape_is_rejected() :void {
		$subject = new AltChaV2Pbkdf2();

		$this->expectException( \Exception::class );
		$subject->verifySolution(
			\json_encode( [
				'algorithm' => 'SHA-256',
				'challenge' => 'abc',
				'salt'      => 'def',
			], \JSON_THROW_ON_ERROR ),
			\json_encode( [
				'number' => 10,
			], \JSON_THROW_ON_ERROR ),
			self::SIGNATURE_SECRET,
			$subject->keySignatureSecret( self::SIGNATURE_SECRET ),
			1999999999
		);
	}

	public function provideMalformedSolutions() :array {
		return [
			'missing counter'       => [
				[
					'derivedKey' => \str_repeat( '0', 64 ),
				],
			],
			'missing derived key'   => [
				[
					'counter' => 7,
				],
			],
			'non-integer counter'   => [
				[
					'counter'    => '7',
					'derivedKey' => \str_repeat( '0', 64 ),
				],
			],
			'negative counter'      => [
				[
					'counter'    => -1,
					'derivedKey' => \str_repeat( '0', 64 ),
				],
			],
			'out-of-range counter'  => [
				[
					'counter'    => 0x100000000,
					'derivedKey' => \str_repeat( '0', 64 ),
				],
			],
			'non-hex derived key'   => [
				[
					'counter'    => 7,
					'derivedKey' => 'not-hex',
				],
			],
		];
	}

	/**
	 * @return array{parameters:array<string,mixed>,signature:string}
	 */
	private function buildChallenge( AltChaV2Pbkdf2 $subject, int $counter, int $expiresAt ) :array {
		return $subject->buildChallenge(
			self::SIGNATURE_SECRET,
			$subject->keySignatureSecret( self::SIGNATURE_SECRET ),
			2,
			$counter,
			$expiresAt,
			self::NONCE,
			self::SALT
		);
	}

	/**
	 * @param array{parameters:array<string,mixed>,signature:string} $challenge
	 * @return array{counter:int,derivedKey:string}
	 */
	private function buildSolution( AltChaV2Pbkdf2 $subject, array $challenge, int $counter ) :array {
		return [
			'counter'    => $counter,
			'derivedKey' => $subject->deriveKeyHex( $challenge[ 'parameters' ], $counter ),
		];
	}
}
