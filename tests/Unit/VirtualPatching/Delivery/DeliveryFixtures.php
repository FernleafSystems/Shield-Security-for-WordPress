<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Delivery;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery\{
	RuleFileVerifier,
	TrustedKeys
};

/**
 * Signs rule files as the orchestrator does, with the shared vectors' fixed test seeds: 0x01 is epoch 1, 0x02
 * epoch 2, and 0x03 a key no site trusts. Never a real key.
 */
trait DeliveryFixtures {

	private static string $issuedAt = '2026-10-23T09:00:00.000Z';

	private static int $nowMs = 1792756800000;

	private static function vectors() :string {
		return \dirname( __DIR__, 3 ).'/fixtures/rule-delivery-v1/';
	}

	private static function testKey( int $fill ) :array {
		$pair = \sodium_crypto_sign_seed_keypair( \str_repeat( \chr( $fill ), 32 ) );
		$public = \sodium_crypto_sign_publickey( $pair );
		return [ 'keyId' => TrustedKeys::keyId( $public ), 'secret' => \sodium_crypto_sign_secretkey( $pair ), 'public' => $public ];
	}

	/**
	 * @param array<int, int> $epochs each trusted key's epoch, by its seed
	 */
	private static function testKeys( array $epochs = [ 1 => 1, 2 => 2 ] ) :TrustedKeys {
		$list = [];
		foreach ( $epochs as $fill => $epoch ) {
			$key = self::testKey( $fill );
			$list[] = [ 'keyId' => $key[ 'keyId' ], 'epoch' => $epoch, 'publicKey' => \base64_encode( $key[ 'public' ] ) ];
		}
		return new TrustedKeys( $list );
	}

	private static function signed( string $payload, int $fill = 2, string $context = RuleFileVerifier::CONTEXT ) :string {
		$key = self::testKey( $fill );
		return (string)\json_encode( [
			'format'    => RuleFileVerifier::FORMAT,
			'keyId'     => $key[ 'keyId' ],
			'context'   => $context,
			'payload'   => \base64_encode( $payload ),
			'signature' => \base64_encode( \sodium_crypto_sign_detached( RuleFileVerifier::FORMAT."\n{$context}\n".$payload, $key[ 'secret' ] ) ),
		], \JSON_UNESCAPED_SLASHES );
	}

	/**
	 * A delivered rule as the orchestrator publishes it, with any member replaced.
	 */
	private static function publishedRule( string $id = 'candidate-1', array $overrides = [] ) :array {
		return \array_replace( [
			'id'                     => $id,
			'mode'                   => 'log',
			'title'                  => 'Example <= 1.4.2 - Synthetic weakness',
			'url'                    => 'https://www.wordfence.com/threat-intel/vulnerabilities/id/00000000-0000-4000-8000-000000000001',
			'copyrights'             => [
				[ 'notice' => 'Copyright Example', 'license' => 'Licence.', 'licenseUrl' => 'https://example.org/licence' ],
			],
			'publicVulnerabilityIds' => [ [ 'authority' => 'CVE', 'identifier' => 'CVE-2026-0001' ] ],
			'affectedVersionRanges'  => [
				[ 'firstAffectedVersion' => '1.0', 'lastAffectedVersion' => '1.4.2', 'firstKnownFixedVersion' => '1.4.3' ],
			],
			'conditions'             => [
				[ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/wp-admin/admin-ajax.php' ],
				[ 'kind' => 'parameter_value', 'negate' => false, 'source' => 'form', 'name' => 'action', 'operator' => 'equals', 'value' => 'example_export' ],
			],
		], $overrides );
	}

	/**
	 * @param array[] $rules
	 */
	private static function ruleFile( string $kind, string $slug, int $sequence, array $rules = [], int $fill = 2, int $schema = 7 ) :string {
		return self::signed( (string)\json_encode( [
			'asset'    => [ 'kind' => $kind, 'slug' => $slug ],
			'schema'   => $schema,
			'sequence' => $sequence,
			'issuedAt' => self::$issuedAt,
			'rules'    => $rules,
		], \JSON_UNESCAPED_SLASHES ), $fill );
	}
}
