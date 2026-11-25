<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey\CredentialPublicKeyPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support\PasskeyFixtureLoader;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Passkey\Support\BuildsPasskeyKeys;
use Webauthn\PublicKeyCredentialSource;

class CredentialPublicKeyPolicyTest extends BaseUnitTest {

	use BuildsPasskeyKeys;

	private const ALG_RS256 = -257;
	private const ALG_PS256 = -37;
	private const ALG_ES256 = -7;
	private const ALG_EDDSA = -8;
	private const EXPONENT_65537 = "\x01\x00\x01";

	public function test_genuine_fixture_passkeys_are_accepted() :void {
		$records = [
			'RS256 sign-in'        => PasskeyFixtureLoader::legacyRecord(),
			'Ed25519 registration' => PasskeyFixtureLoader::load()[ 'registration' ][ 'verified_record' ],
		];

		foreach ( $records as $name => $record ) {
			$this->assertTrue( $this->policy()->accepts( PublicKeyCredentialSource::createFromArray( $record ) ), $name );
		}
	}

	public function test_non_rsa_keys_are_accepted() :void {
		$keys = [
			'ES256'   => $this->coseKey( [
				1  => 2,
				3  => self::ALG_ES256,
				-1 => 1,
				-2 => \str_repeat( "\x11", 32 ),
				-3 => \str_repeat( "\x22", 32 ),
			] ),
			'Ed25519' => $this->coseKey( [
				1  => 1,
				3  => self::ALG_EDDSA,
				-1 => 6,
				-2 => \str_repeat( "\x33", 32 ),
			] ),
		];

		foreach ( $keys as $name => $key ) {
			$this->assertTrue( $this->policy()->accepts( $this->fixtureSourceWithKey( $key ) ), $name );
		}
	}

	/**
	 * Each pair differs only by the modulus size, so the accepted key is read as RSA and checked, not passed through.
	 */
	public function test_rsa_modulus_is_accepted_up_to_4096_bits_whatever_the_algorithm() :void {
		foreach ( [ self::ALG_RS256, self::ALG_PS256 ] as $alg ) {
			$this->assertTrue( $this->acceptsRsa( $alg, $this->modulus( 512 ), self::EXPONENT_65537 ), (string)$alg );
			$this->assertFalse( $this->acceptsRsa( $alg, $this->modulus( 513 ), self::EXPONENT_65537 ), (string)$alg );
		}
	}

	public function test_leading_zero_bytes_do_not_count_towards_the_size_bound() :void {
		$this->assertTrue( $this->acceptsRsa( self::ALG_RS256, "\0".$this->modulus( 512 ), "\0".self::EXPONENT_65537 ) );
		$this->assertFalse( $this->acceptsRsa( self::ALG_RS256, "\0".$this->modulus( 513 ), "\0".self::EXPONENT_65537 ) );
	}

	/**
	 * @dataProvider providerRsaExponents
	 */
	public function test_rsa_exponent_must_be_odd_at_least_3_and_no_longer_than_the_modulus( string $exponent, bool $accepted ) :void {
		$this->assertSame( $accepted, $this->acceptsRsa( self::ALG_RS256, $this->modulus( 256 ), $exponent ) );
	}

	public static function providerRsaExponents() :array {
		return [
			'65537'                    => [ self::EXPONENT_65537, true ],
			'3'                        => [ "\x03", true ],
			'as long as the modulus'   => [ \str_repeat( "\xff", 256 ), true ],
			'zero'                     => [ "\x00", false ],
			'one'                      => [ "\x01", false ],
			'even'                     => [ "\x01\x00\x00", false ],
			'longer than the modulus'  => [ \str_repeat( "\xff", 257 ), false ],
		];
	}

	private function acceptsRsa( int $alg, string $n, string $e ) :bool {
		return $this->policy()->accepts( $this->fixtureSourceWithKey( $this->rsaCoseKey( $alg, $n, $e ) ) );
	}

	private function policy() :CredentialPublicKeyPolicy {
		return new CredentialPublicKeyPolicy();
	}
}
