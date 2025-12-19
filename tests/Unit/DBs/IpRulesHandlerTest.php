<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Tests for IP Rules Handler - validates IP rule type handling.
 * 
 * These tests ensure the IP blocking/whitelisting type system is correctly implemented.
 */
class IpRulesHandlerTest extends TestCase {

	// =========================================================================
	// TYPE CONSTANTS TESTS
	// =========================================================================

	public function testAllTypeConstantsAreDefined() :void {
		$this->assertSame( 'AW', Handler::T_AUTO_BYPASS, 'Auto bypass constant should be AW' );
		$this->assertSame( 'MW', Handler::T_MANUAL_BYPASS, 'Manual bypass constant should be MW' );
		$this->assertSame( 'MB', Handler::T_MANUAL_BLOCK, 'Manual block constant should be MB' );
		$this->assertSame( 'AB', Handler::T_AUTO_BLOCK, 'Auto block constant should be AB' );
		$this->assertSame( 'CS', Handler::T_CROWDSEC, 'CrowdSec constant should be CS' );
	}

	public function testTypeConstantsAreUnique() :void {
		$types = [
			Handler::T_AUTO_BYPASS,
			Handler::T_MANUAL_BYPASS,
			Handler::T_MANUAL_BLOCK,
			Handler::T_AUTO_BLOCK,
			Handler::T_CROWDSEC,
		];

		$uniqueTypes = array_unique( $types );
		$this->assertCount(
			count( $types ),
			$uniqueTypes,
			'All type constants should have unique values'
		);
	}

	// =========================================================================
	// IsValidType TESTS
	// =========================================================================

	/**
	 * @dataProvider providerValidTypes
	 */
	public function testIsValidTypeReturnsTrueForValidTypes( string $type ) :void {
		$this->assertTrue(
			Handler::IsValidType( $type ),
			sprintf( "Type '%s' should be valid", $type )
		);
	}

	public static function providerValidTypes() :array {
		return [
			'auto bypass' => [ Handler::T_AUTO_BYPASS ],
			'manual bypass' => [ Handler::T_MANUAL_BYPASS ],
			'manual block' => [ Handler::T_MANUAL_BLOCK ],
			'auto block' => [ Handler::T_AUTO_BLOCK ],
			'crowdsec' => [ Handler::T_CROWDSEC ],
		];
	}

	/**
	 * @dataProvider providerInvalidTypes
	 */
	public function testIsValidTypeReturnsFalseForInvalidTypes( string $type ) :void {
		$this->assertFalse(
			Handler::IsValidType( $type ),
			sprintf( "Type '%s' should be invalid", $type )
		);
	}

	public static function providerInvalidTypes() :array {
		return [
			'empty string' => [ '' ],
			'unknown code' => [ 'XX' ],
			'lowercase valid' => [ 'ab' ],
			'partial match' => [ 'A' ],
			'sql injection attempt' => [ "'; DROP TABLE--" ],
			'numeric' => [ '123' ],
			'whitespace' => [ '  ' ],
		];
	}

	// =========================================================================
	// BYPASS vs BLOCK TYPE TESTS
	// =========================================================================

	public function testBypassTypesAreDistinctFromBlockTypes() :void {
		$bypassTypes = [
			Handler::T_AUTO_BYPASS,
			Handler::T_MANUAL_BYPASS,
		];

		$blockTypes = [
			Handler::T_MANUAL_BLOCK,
			Handler::T_AUTO_BLOCK,
			Handler::T_CROWDSEC,
		];

		// Ensure no overlap between bypass and block types
		$overlap = array_intersect( $bypassTypes, $blockTypes );
		$this->assertEmpty( $overlap, 'Bypass and block types should not overlap' );
	}

	public function testManualTypesAreDistinctFromAutoTypes() :void {
		$manualTypes = [
			Handler::T_MANUAL_BYPASS,
			Handler::T_MANUAL_BLOCK,
		];

		$autoTypes = [
			Handler::T_AUTO_BYPASS,
			Handler::T_AUTO_BLOCK,
		];

		$overlap = array_intersect( $manualTypes, $autoTypes );
		$this->assertEmpty( $overlap, 'Manual and auto types should not overlap' );
	}

	// =========================================================================
	// TYPE IDENTIFICATION TESTS (useful for categorization)
	// =========================================================================

	public function testCanIdentifyBypassTypes() :void {
		$bypassTypes = [
			Handler::T_AUTO_BYPASS,
			Handler::T_MANUAL_BYPASS,
		];

		foreach ( $bypassTypes as $type ) {
			$this->assertStringEndsWith( 'W', $type, 'Bypass types should end with W (whitelist)' );
		}
	}

	public function testCanIdentifyBlockTypes() :void {
		$blockTypes = [
			Handler::T_MANUAL_BLOCK,
			Handler::T_AUTO_BLOCK,
		];

		foreach ( $blockTypes as $type ) {
			$this->assertStringEndsWith( 'B', $type, 'Block types should end with B (block)' );
		}
	}

	public function testCanIdentifyManualTypes() :void {
		$manualTypes = [
			Handler::T_MANUAL_BYPASS,
			Handler::T_MANUAL_BLOCK,
		];

		foreach ( $manualTypes as $type ) {
			$this->assertStringStartsWith( 'M', $type, 'Manual types should start with M' );
		}
	}

	public function testCanIdentifyAutoTypes() :void {
		$autoTypes = [
			Handler::T_AUTO_BYPASS,
			Handler::T_AUTO_BLOCK,
		];

		foreach ( $autoTypes as $type ) {
			$this->assertStringStartsWith( 'A', $type, 'Auto types should start with A' );
		}
	}
}

