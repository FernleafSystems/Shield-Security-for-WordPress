<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Tests for EnumMatchTypes to ensure match type constants and helpers are correct.
 */
class EnumMatchTypesTest extends TestCase {

	public function testAllMatchTypeConstantsAreDefined() :void {
		$expectedConstants = [
			'MATCH_TYPE_EQUALS',
			'MATCH_TYPE_EQUALS_I',
			'MATCH_TYPE_CONTAINS',
			'MATCH_TYPE_CONTAINS_I',
			'MATCH_TYPE_IP_EQUALS',
			'MATCH_TYPE_IP_RANGE',
			'MATCH_TYPE_REGEX',
			'MATCH_TYPE_LESS_THAN',
			'MATCH_TYPE_GREATER_THAN',
		];

		$reflection = new \ReflectionClass( EnumMatchTypes::class );
		$constants = $reflection->getConstants();

		foreach ( $expectedConstants as $constant ) {
			$this->assertArrayHasKey( $constant, $constants, "Constant {$constant} should be defined" );
		}
	}

	public function testMatchTypesForStringsReturnsCorrectTypes() :void {
		$stringTypes = EnumMatchTypes::MatchTypesForStrings();

		$this->assertContains( EnumMatchTypes::MATCH_TYPE_EQUALS, $stringTypes );
		$this->assertContains( EnumMatchTypes::MATCH_TYPE_EQUALS_I, $stringTypes );
		$this->assertContains( EnumMatchTypes::MATCH_TYPE_CONTAINS, $stringTypes );
		$this->assertContains( EnumMatchTypes::MATCH_TYPE_CONTAINS_I, $stringTypes );
		$this->assertContains( EnumMatchTypes::MATCH_TYPE_REGEX, $stringTypes );

		// IP types should not be in string types
		$this->assertNotContains( EnumMatchTypes::MATCH_TYPE_IP_EQUALS, $stringTypes );
		$this->assertNotContains( EnumMatchTypes::MATCH_TYPE_IP_RANGE, $stringTypes );
	}

	public function testMatchTypesForIPsReturnsCorrectTypes() :void {
		$ipTypes = EnumMatchTypes::MatchTypesForIPs();

		$this->assertContains( EnumMatchTypes::MATCH_TYPE_IP_EQUALS, $ipTypes );
		$this->assertContains( EnumMatchTypes::MATCH_TYPE_IP_RANGE, $ipTypes );

		// String types should not be in IP types
		$this->assertNotContains( EnumMatchTypes::MATCH_TYPE_CONTAINS, $ipTypes );
		$this->assertNotContains( EnumMatchTypes::MATCH_TYPE_REGEX, $ipTypes );
	}

	public function testMatchTypesForNumbersReturnsCorrectTypes() :void {
		$numericTypes = EnumMatchTypes::MatchTypesForNumbers();

		$this->assertContains( EnumMatchTypes::MATCH_TYPE_EQUALS, $numericTypes );
		$this->assertContains( EnumMatchTypes::MATCH_TYPE_LESS_THAN, $numericTypes );
		$this->assertContains( EnumMatchTypes::MATCH_TYPE_GREATER_THAN, $numericTypes );

		// Regex and contains don't make sense for pure numeric comparison
		$this->assertNotContains( EnumMatchTypes::MATCH_TYPE_REGEX, $numericTypes );
		$this->assertNotContains( EnumMatchTypes::MATCH_TYPE_CONTAINS, $numericTypes );
	}

	public function testMatchTypeConstantValuesAreUnique() :void {
		$reflection = new \ReflectionClass( EnumMatchTypes::class );
		$constants = $reflection->getConstants();

		// Filter to only MATCH_TYPE_ constants
		$matchTypeConstants = array_filter(
			$constants,
			fn( $key ) => str_starts_with( $key, 'MATCH_TYPE_' ),
			ARRAY_FILTER_USE_KEY
		);

		$values = array_values( $matchTypeConstants );
		$uniqueValues = array_unique( $values );

		$this->assertCount(
			count( $values ),
			$uniqueValues,
			'All MATCH_TYPE_ constant values should be unique'
		);
	}
}

