<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\RuleEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class RuleEvaluatorTest extends TestCase {

	private const CASES_FILE = __DIR__.'/../../fixtures/virtual-patching/evaluator-cases.json';

	#[DataProvider( 'providerSharedCases' )]
	public function testSharedCase( array $conditions, array $facts, string $expected ) :void {
		$this->assertSame( $expected, ( new RuleEvaluator() )->evaluate( $this->rule( $conditions ), $this->facts( $facts ) ) );
	}

	public static function providerSharedCases() :array {
		$cases = [];
		foreach ( \json_decode( (string)\file_get_contents( self::CASES_FILE ), true, 512, \JSON_THROW_ON_ERROR )[ 'cases' ] as $case ) {
			$cases[ $case[ 'name' ] ] = [ $case[ 'conditions' ], $case[ 'facts' ], $case[ 'expected' ] ];
		}
		return $cases;
	}

	public function testRequiredFactsFollowConditionsAndParameterSources() :void {
		$rule = $this->rule( [
			[ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/' ],
			[
				'kind'       => 'or',
				'conditions' => [
					[ 'kind' => 'parameter_exists', 'negate' => false, 'source' => 'either', 'name' => 'a' ],
					[ 'kind' => 'user_capability', 'negate' => true, 'capability' => 'manage_options' ],
				],
			],
		] );
		$required = ( new RuleEvaluator() )->requiredFacts( $rule );
		\sort( $required );
		$this->assertSame( [ 'form', 'path', 'query', 'user_can' ], $required );
	}

	public function testRequestOnlyRulesAreEarlyCapable() :void {
		$evaluator = new RuleEvaluator();
		$this->assertTrue( $evaluator->isEarlyCapable( $this->rule( [
			[ 'kind' => 'script', 'negate' => false, 'operator' => 'equals', 'value' => 'upload.php' ],
			[ 'kind' => 'plugin_active', 'negate' => false, 'pluginFile' => 'example/example.php' ],
		] ) ) );
		$this->assertFalse( $evaluator->isEarlyCapable( $this->rule( [
			[ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/wp-admin/admin-ajax.php' ],
			[ 'kind' => 'wp_is_admin', 'negate' => false ],
		] ) ) );
		$this->assertFalse( $evaluator->isEarlyCapable( $this->rule( [
			[ 'kind' => 'rest_route', 'negate' => false, 'namespace' => 'example/v1', 'operator' => 'equals_i', 'value' => '' ],
		] ) ) );
	}

	public function testMissingFactIsRejected() :void {
		$this->expectException( \InvalidArgumentException::class );
		( new RuleEvaluator() )->evaluate(
			$this->rule( [ [ 'kind' => 'user_capability', 'negate' => false, 'capability' => 'manage_options' ] ] ),
			[ 'path' => '/' ]
		);
	}

	#[DataProvider( 'providerMalformedConditions' )]
	public function testMalformedConditionIsRejectedBeforeEvaluation( array $condition ) :void {
		$this->expectException( \InvalidArgumentException::class );
		( new RuleEvaluator() )->evaluate( $this->rule( [ $condition ] ), [
			'method' => 'GET',
			'path'   => '/',
			'script' => 'index.php',
			'query'  => [],
			'form'   => [],
		] );
	}

	public static function providerMalformedConditions() :array {
		return [
			'unknown kind'         => [ [ 'kind' => 'cookie', 'negate' => false ] ],
			'missing value'        => [ [ 'kind' => 'path', 'negate' => false, 'operator' => 'equals' ] ],
			'missing operator'     => [ [ 'kind' => 'path', 'negate' => false, 'value' => '/' ] ],
			'value not a string'   => [ [ 'kind' => 'method', 'negate' => false, 'value' => [ 'GET' ] ] ],
			'unknown operator'     => [ [ 'kind' => 'path', 'negate' => false, 'operator' => 'starts_with', 'value' => '/' ] ],
			'unknown source'       => [ [ 'kind' => 'parameter_exists', 'negate' => false, 'source' => 'cookie', 'name' => 'a' ] ],
			'either for a value'   => [ [ 'kind' => 'parameter_value', 'negate' => false, 'source' => 'either', 'name' => 'a', 'operator' => 'equals', 'value' => 'b' ] ],
			'negate not a boolean' => [ [ 'kind' => 'path', 'negate' => 'yes', 'operator' => 'equals', 'value' => '/' ] ],
			'or without branches'  => [ [ 'kind' => 'or' ] ],
		];
	}

	public function testPcreSettingsAreRestored() :void {
		$settings = [ 'pcre.backtrack_limit', 'pcre.recursion_limit', 'pcre.jit' ];
		$before = \array_map( '\ini_get', $settings );

		( new RuleEvaluator() )->evaluate(
			$this->rule( [ [ 'kind' => 'path', 'negate' => false, 'operator' => 'regex', 'value' => '#(#' ] ] ),
			[ 'path' => '/' ]
		);

		$this->assertSame( $before, \array_map( '\ini_get', $settings ) );
	}

	#[DataProvider( 'providerRequestUris' )]
	public function testPathFromRequestUri( string $requestUri, string $expected ) :void {
		$this->assertSame( $expected, RuleEvaluator::pathFromRequestUri( $requestUri ) );
	}

	public static function providerRequestUris() :array {
		return [
			'plain path'               => [ '/wp-admin/admin-ajax.php', '/wp-admin/admin-ajax.php' ],
			'query removed'            => [ '/wp-admin/admin-ajax.php?action=x&y=?', '/wp-admin/admin-ajax.php' ],
			'empty'                    => [ '', '/' ],
			'query only'               => [ '?p=1', '/' ],
			'percent-encoded'          => [ '/wp-content/plugins/example/%75pload.php', '/wp-content/plugins/example/upload.php' ],
			'decoded once'             => [ '/a%2541', '/a%41' ],
			'repeated slashes'         => [ '//wp-content///plugins/example/upload.php', '/wp-content/plugins/example/upload.php' ],
			'absolute form'            => [ 'http://example.test/wp-login.php?x=1', '/wp-login.php' ],
			'absolute form, no path'   => [ 'https://example.test', '/' ],
			'encoded question mark'    => [ '/a%3Fb?c', '/a?b' ],
			'plus is not a space'      => [ '/a+b', '/a+b' ],
		];
	}

	public function testScriptFromServer() :void {
		$this->assertSame( 'admin-ajax.php', RuleEvaluator::scriptFromServer( [
			'SCRIPT_NAME'     => '/wp-admin/admin-ajax.php',
			'SCRIPT_FILENAME' => '/var/www/html/wp-admin/other.php',
		] ) );
		$this->assertSame( 'upload.php', RuleEvaluator::scriptFromServer( [
			'SCRIPT_NAME'     => '',
			'SCRIPT_FILENAME' => '/var/www/html/wp-content/plugins/example/upload.php',
		] ) );
		$this->assertSame( '', RuleEvaluator::scriptFromServer( [] ) );
	}

	public function testRequestFacts() :void {
		$facts = RuleEvaluator::requestFacts(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin//admin-ajax.php?action=a',
				'SCRIPT_NAME'    => '/wp-admin/admin-ajax.php',
			],
			[ 'action' => 'a' ],
			[ 'nonce' => 'n' ]
		);
		$this->assertSame( [
			'method' => 'POST',
			'path'   => '/wp-admin/admin-ajax.php',
			'script' => 'admin-ajax.php',
			'query'  => [ 'action' => 'a' ],
			'form'   => [ 'nonce' => 'n' ],
		], $facts );
	}

	private function rule( array $conditions ) :array {
		return [
			'id'         => 'test-rule',
			'advisories' => [ 'CVE-0000-0000' ],
			'conditions' => $conditions,
		];
	}

	/**
	 * The shared cases describe callables as lists.
	 */
	private function facts( array $facts ) :array {
		if ( \array_key_exists( 'user_capabilities', $facts ) ) {
			$capabilities = $facts[ 'user_capabilities' ];
			$facts[ 'user_can' ] = fn( string $capability ) :bool => \in_array( $capability, $capabilities, true );
			unset( $facts[ 'user_capabilities' ] );
		}
		if ( \array_key_exists( 'rest_params', $facts ) ) {
			$params = $facts[ 'rest_params' ];
			$facts[ 'rest_has_param' ] = fn( string $name ) :bool => \in_array( $name, $params, true );
			unset( $facts[ 'rest_params' ] );
		}
		return $facts;
	}
}
