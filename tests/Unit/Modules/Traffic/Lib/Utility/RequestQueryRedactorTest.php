<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Traffic\Lib\Utility;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility\RequestQueryRedactor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RequestQueryRedactorTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'apply_filters' )->alias( static fn( string $tag, $value ) => $value );
	}

	public function test_redacts_default_sensitive_keys_and_preserves_ordinary_values() :void {
		$query = 'reauth=1&token=token-secret&_wpnonce=nonce-secret&KEY=reset-secret&login=admin';

		$redacted = ( new RequestQueryRedactor() )->redact( $query );

		$this->assertQueryValue( $redacted, 'reauth', '1' );
		$this->assertQueryValue( $redacted, 'login', 'admin' );
		$this->assertQueryValue( $redacted, 'token', 'redacted' );
		$this->assertQueryValue( $redacted, '_wpnonce', 'redacted' );
		$this->assertQueryValue( $redacted, 'KEY', 'redacted' );
		$this->assertStringNotContainsString( 'token-secret', $redacted );
		$this->assertStringNotContainsString( 'nonce-secret', $redacted );
		$this->assertStringNotContainsString( 'reset-secret', $redacted );
	}

	public function test_redacts_nested_sensitive_query_key_segments() :void {
		$redacted = ( new RequestQueryRedactor() )->redact( 'payload%5Btoken%5D=nested-secret&safe=value' );

		$this->assertQueryValue( $redacted, 'payload%5Btoken%5D', 'redacted' );
		$this->assertQueryValue( $redacted, 'safe', 'value' );
		$this->assertStringNotContainsString( 'nested-secret', $redacted );
	}

	public function test_redacts_sensitive_keys_with_semicolon_query_separators() :void {
		$redacted = ( new RequestQueryRedactor() )->redact( 'safe=1;token=token-secret&ok=2;secret=secret-value' );

		$this->assertQueryValue( $redacted, 'safe', '1' );
		$this->assertQueryValue( $redacted, 'token', 'redacted' );
		$this->assertQueryValue( $redacted, 'ok', '2' );
		$this->assertQueryValue( $redacted, 'secret', 'redacted' );
		$this->assertStringNotContainsString( 'token-secret', $redacted );
		$this->assertStringNotContainsString( 'secret-value', $redacted );
	}

	public function test_filters_can_add_sensitive_keys_and_adjust_redacted_query() :void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) {
				if ( $tag === RequestQueryRedactor::FILTER_SENSITIVE_QUERY_KEYS ) {
					return \array_merge( (array)$value, [ 'customer_magic' ] );
				}
				if ( $tag === RequestQueryRedactor::FILTER_REDACTED_QUERY ) {
					return $value.'&filtered=1';
				}
				return $value;
			}
		);

		$redacted = ( new RequestQueryRedactor() )->redact( 'customer_magic=custom-secret&safe=value' );

		$this->assertQueryValue( $redacted, 'customer_magic', 'redacted' );
		$this->assertQueryValue( $redacted, 'safe', 'value' );
		$this->assertQueryValue( $redacted, 'filtered', '1' );
		$this->assertStringNotContainsString( 'custom-secret', $redacted );
	}

	private function assertQueryValue( string $query, string $key, string $expectedValue ) :void {
		$pairs = $this->queryPairs( $query );

		$this->assertArrayHasKey( $key, $pairs );
		$this->assertContains( $expectedValue, $pairs[ $key ] );
	}

	/**
	 * @return array<string,list<string>>
	 */
	private function queryPairs( string $query ) :array {
		$pairs = [];
		$parts = \preg_split( '#[&;]#', $query, -1, \PREG_SPLIT_NO_EMPTY );
		foreach ( \is_array( $parts ) ? $parts : [] as $part ) {
			$separatorPos = \strpos( $part, '=' );
			$key = $separatorPos === false ? $part : \substr( $part, 0, $separatorPos );
			$pairs[ $key ][] = $separatorPos === false ? '' : \substr( $part, $separatorPos + 1 );
		}

		return $pairs;
	}
}
