<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeyCredentialDataNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PasskeyCredentialDataNormalizerTest extends BaseUnitTest {

	/**
	 * @dataProvider classLikeTrustPathTypesProvider
	 */
	public function test_class_like_trust_path_types_without_trust_material_normalize_to_empty( string $type ) :void {
		$normalized = $this->normalizer()->normalize( [
			'trustPath' => [
				'type' => $type,
			],
		] );

		$this->assertSame( 'empty', $normalized[ 'trustPath' ][ 'type' ] ?? null );
	}

	/**
	 * @dataProvider trustMaterialProvider
	 */
	public function test_trust_material_normalizes_to_alias_independent_of_type( array $trustPath, string $expected ) :void {
		$normalized = $this->normalizer()->normalize( [
			'trustPath' => $trustPath,
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
	 * @return array<string, array{0:string}>
	 */
	public function classLikeTrustPathTypesProvider() :array {
		return [
			'unprefixed empty'             => [ 'Webauthn\\TrustPath\\EmptyTrustPath' ],
			'prefixed empty'               => [ 'AptowebDeps\\Webauthn\\TrustPath\\EmptyTrustPath' ],
			'custom prefixed empty'        => [ 'CustomDeps\\Webauthn\\TrustPath\\EmptyTrustPath' ],
			'leading slash empty'          => [ '\\Webauthn\\TrustPath\\EmptyTrustPath' ],
			'renamed implementation class' => [ 'RenamedVendor\\Passkeys\\Trust\\DefinitelyNotCurrentClass' ],
		];
	}

	/**
	 * @return array<string, array{0:array,1:string}>
	 */
	public function trustMaterialProvider() :array {
		return [
			'certificate material with old class type' => [
				[
					'type' => 'Webauthn\\TrustPath\\CertificateTrustPath',
					'x5c'  => [
						'certificate-data',
					],
				],
				'x5c',
			],
			'certificate material with arbitrary class type' => [
				[
					'type' => 'RenamedVendor\\Trust\\Anything',
					'x5c'  => [
						'certificate-data',
					],
				],
				'x5c',
			],
			'certificate material without type' => [
				[
					'x5c' => [
						'certificate-data',
					],
				],
				'x5c',
			],
			'ecdaa material with old class type' => [
				[
					'type'       => 'Webauthn\\TrustPath\\EcdaaKeyIdTrustPath',
					'ecdaaKeyId' => 'ecdaa-key-id',
				],
				'ecdaa_key_id',
			],
			'ecdaa material without type' => [
				[
					'ecdaaKeyId' => 'ecdaa-key-id',
				],
				'ecdaa_key_id',
			],
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
			'missing trust path type and material' => [
				[
					'trustPath' => [],
				],
			],
			'non-string trust path type' => [
				[
					'trustPath' => [
						'type' => 123,
					],
				],
			],
			'unknown non-class trust path type' => [
				[
					'trustPath' => [
						'type' => 'not_a_known_type',
					],
				],
			],
			'malformed class-like trust path type' => [
				[
					'trustPath' => [
						'type' => 'Webauthn\\TrustPath\\Broken-Type',
					],
				],
			],
			'path-like trust path type' => [
				[
					'trustPath' => [
						'type' => 'Webauthn/TrustPath/EmptyTrustPath',
					],
				],
			],
		];
	}

	private function normalizer() :PasskeyCredentialDataNormalizer {
		return new PasskeyCredentialDataNormalizer();
	}
}
