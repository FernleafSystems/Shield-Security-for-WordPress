<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeyCredentialDataNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PasskeyCredentialDataNormalizerTest extends BaseUnitTest {

	/**
	 * @dataProvider classTrustPathTypesProvider
	 */
	public function test_class_trust_path_types_normalize_to_aliases( string $type, string $expected ) :void {
		$normalized = $this->normalizer()->normalize( [
			'trustPath' => [
				'type' => $type,
			],
		] );

		$this->assertSame( $expected, $normalized[ 'trustPath' ][ 'type' ] ?? null );
	}

	public function test_normalize_only_changes_trust_path_type() :void {
		$credentialData = [
			'publicKeyCredentialId' => 'credential-id',
			'type'                  => 'public-key',
			'counter'               => 7,
			'trustPath'             => [
				'type' => 'Webauthn\\TrustPath\\CertificateTrustPath',
				'x5c'  => [
					'certificate-data',
				],
			],
		];

		$normalized = $this->normalizer()->normalize( $credentialData );

		$this->assertSame( 'x5c', $normalized[ 'trustPath' ][ 'type' ] ?? null );
		unset( $credentialData[ 'trustPath' ][ 'type' ], $normalized[ 'trustPath' ][ 'type' ] );
		$this->assertSame( $credentialData, $normalized );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public function classTrustPathTypesProvider() :array {
		return [
			'unprefixed empty'       => [ 'Webauthn\\TrustPath\\EmptyTrustPath', 'empty' ],
			'prefixed empty'         => [ 'AptowebDeps\\Webauthn\\TrustPath\\EmptyTrustPath', 'empty' ],
			'custom prefixed empty'  => [ 'CustomDeps\\Webauthn\\TrustPath\\EmptyTrustPath', 'empty' ],
			'leading slash empty'    => [ '\\Webauthn\\TrustPath\\EmptyTrustPath', 'empty' ],
			'unprefixed certificate' => [ 'Webauthn\\TrustPath\\CertificateTrustPath', 'x5c' ],
			'prefixed certificate'   => [ 'AptowebDeps\\Webauthn\\TrustPath\\CertificateTrustPath', 'x5c' ],
			'unprefixed ecdaa'       => [ 'Webauthn\\TrustPath\\EcdaaKeyIdTrustPath', 'ecdaa_key_id' ],
			'prefixed ecdaa'         => [ 'AptowebDeps\\Webauthn\\TrustPath\\EcdaaKeyIdTrustPath', 'ecdaa_key_id' ],
		];
	}

	/**
	 * @dataProvider unchangedCredentialDataProvider
	 */
	public function test_unmapped_or_malformed_credential_data_is_unchanged( array $credentialData ) :void {
		$this->assertSame( $credentialData, $this->normalizer()->normalize( $credentialData ) );
	}

	/**
	 * @return array<string, array{0:array}>
	 */
	public function unchangedCredentialDataProvider() :array {
		return [
			'existing empty alias' => [
				[
					'trustPath' => [
						'type' => 'empty',
					],
				],
			],
			'existing certificate alias' => [
				[
					'trustPath' => [
						'type' => 'x5c',
					],
				],
			],
			'existing ecdaa alias' => [
				[
					'trustPath' => [
						'type' => 'ecdaa_key_id',
					],
				],
			],
			'unknown class' => [
				[
					'trustPath' => [
						'type' => 'Vendor\\Package\\UnknownTrustPath',
					],
				],
			],
			'unknown package empty trust path class' => [
				[
					'trustPath' => [
						'type' => 'Vendor\\Package\\EmptyTrustPath',
					],
				],
			],
			'missing trust path' => [
				[
					'publicKeyCredentialId' => 'credential-id',
				],
			],
			'non-array trust path' => [
				[
					'trustPath' => 'not-an-array',
				],
			],
			'missing trust path type' => [
				[
					'trustPath' => [
						'x5c' => [],
					],
				],
			],
			'non-string trust path type' => [
				[
					'trustPath' => [
						'type' => 123,
					],
				],
			],
		];
	}

	private function normalizer() :PasskeyCredentialDataNormalizer {
		return new PasskeyCredentialDataNormalizer();
	}
}
