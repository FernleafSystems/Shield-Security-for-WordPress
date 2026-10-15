<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Ruleset;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\PatchValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class PatchValidatorTest extends TestCase {

	use PatchFixtures;

	private const PATH = [ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/wp-admin/admin-ajax.php' ];
	private const REST = [ 'kind' => 'rest_route', 'negate' => false, 'namespace' => 'example/v1', 'operator' => 'equals_i', 'value' => '/export' ];

	#[DataProvider( 'providerValidPatches' )]
	public function testValidPatch( array $patch ) :void {
		$this->assertSame( [], ( new PatchValidator() )->validate( $patch ) );
	}

	public static function providerValidPatches() :array {
		return [
			'path and form value'           => [ self::patch() ],
			'REST route and parameter'      => [ self::patch( [
				self::REST,
				[ 'kind' => 'rest_parameter_exists', 'negate' => false, 'name' => 'file' ],
			] ) ],
			'or group of request conditions' => [ self::patch( [
				[ 'kind' => 'method', 'negate' => false, 'value' => 'POST' ],
				[ 'kind' => 'or', 'conditions' => [
					[ 'kind' => 'parameter_exists', 'negate' => false, 'source' => 'query', 'name' => 'export' ],
					[ 'kind' => 'parameter_exists', 'negate' => false, 'source' => 'form', 'name' => 'export' ],
				] ],
			] ) ],
			'value from query or form'      => [ self::patch( [
				self::PATH,
				[ 'kind' => 'parameter_value', 'negate' => false, 'source' => 'either', 'name' => 'action', 'operator' => 'equals', 'value' => 'example_export' ],
			] ) ],
			'negated capability'            => [ self::patch( [
				self::PATH,
				[ 'kind' => 'user_capability', 'negate' => true, 'capability' => 'manage_options' ],
			] ) ],
			'theme patch'                   => [ self::patch(
				[ self::PATH, [ 'kind' => 'theme_active', 'negate' => false, 'theme' => 'example-theme' ] ],
				[ 'targetExtension' => [ 'kind' => 'theme', 'slug' => 'example-theme', 'affectedVersionRanges' => [ [ 'firstAffectedVersion' => '1', 'lastAffectedVersion' => '2' ] ] ] ]
			) ],
			'no vulnerability IDs'          => [ self::patch( [], [ 'publicVulnerabilityIds' => [] ] ) ],
			'empty exact value'             => [ self::patch( [
				[ 'kind' => 'rest_route', 'negate' => false, 'namespace' => 'example/v1', 'operator' => 'equals_i', 'value' => '' ],
			] ) ],
		];
	}

	#[DataProvider( 'providerInvalidPatches' )]
	public function testInvalidPatch( array $patch ) :void {
		$this->assertNotEmpty( ( new PatchValidator() )->validate( $patch ) );
	}

	public static function providerInvalidPatches() :array {
		$leaves = static fn( int $count ) :array => \array_map(
			static fn( int $i ) :array => [ 'kind' => 'parameter_exists', 'negate' => false, 'source' => 'query', 'name' => 'p'.$i ],
			\range( 1, $count )
		);
		return [
			'other contract version'          => [ self::patch( [], [ 'contractVersion' => 1 ] ) ],
			'action other than block'         => [ self::patch( [], [ 'virtualPatch' => [ 'action' => 'log', 'matcher' => [ 'conditions' => [ self::PATH ] ] ] ] ) ],
			'vulnerability IDs not a list'    => [ self::patch( [], [ 'publicVulnerabilityIds' => [ 'a' => [ 'authority' => 'CVE', 'identifier' => 'CVE-2026-0001' ] ] ] ) ],
			'no version ranges'               => [ self::patch( [], [ 'targetExtension' => [ 'kind' => 'plugin', 'slug' => 'example', 'affectedVersionRanges' => [] ] ] ) ],
			'unsupported kind'                => [ self::patch( [ self::PATH, [ 'kind' => 'cookie', 'negate' => false, 'name' => 'x' ] ] ) ],
			'unknown field'                   => [ self::patch( [ self::PATH + [ 'extra' => 'x' ] ] ) ],
			'negate not boolean'              => [ self::patch( [ [ 'negate' => 'no' ] + self::PATH ] ) ],
			'no request condition'            => [ self::patch( [
				[ 'kind' => 'method', 'negate' => false, 'value' => 'POST' ],
				[ 'kind' => 'user_capability', 'negate' => true, 'capability' => 'manage_options' ],
			] ) ],
			'only negated request condition'  => [ self::patch( [ [ 'negate' => true ] + self::PATH ] ) ],
			'too many conditions'             => [ self::patch( $leaves( 9 ) ) ],
			'two or groups'                   => [ self::patch( [
				self::PATH,
				[ 'kind' => 'or', 'conditions' => $leaves( 2 ) ],
				[ 'kind' => 'or', 'conditions' => \array_slice( $leaves( 4 ), 2 ) ],
			] ) ],
			'or group with one branch'        => [ self::patch( [ self::PATH, [ 'kind' => 'or', 'conditions' => $leaves( 1 ) ] ] ) ],
			'duplicate condition'             => [ self::patch( [ self::PATH, [ 'negate' => true ] + self::PATH ] ) ],
			'negated REST route'              => [ self::patch( [ self::PATH, [ 'negate' => true ] + self::REST ] ) ],
			'two REST routes'                 => [ self::patch( [ self::REST, [ 'value' => '/other' ] + self::REST ] ) ],
			'REST route in or group'          => [ self::patch( [ self::PATH, [ 'kind' => 'or', 'conditions' => [ self::REST, $leaves( 1 )[ 0 ] ] ] ] ) ],
			'REST route with capability'      => [ self::patch( [ self::REST, [ 'kind' => 'user_capability', 'negate' => true, 'capability' => 'edit_posts' ] ] ) ],
			'REST parameter without route'    => [ self::patch( [ self::PATH, [ 'kind' => 'rest_parameter_exists', 'negate' => false, 'name' => 'file' ] ] ) ],
			'REST regex without i modifier'   => [ self::patch( [ [ 'operator' => 'regex', 'value' => '#^/export$#' ] + self::REST ] ) ],
			'REST value without slash'        => [ self::patch( [ [ 'value' => 'export' ] + self::REST ] ) ],
			'theme condition in plugin patch' => [ self::patch( [ self::PATH, [ 'kind' => 'theme_active', 'negate' => false, 'theme' => 'x' ] ] ) ],
			'exact path without slash'        => [ self::patch( [ [ 'value' => 'wp-login.php' ] + self::PATH ] ) ],
			'script containing a slash'       => [ self::patch( [ [ 'kind' => 'script', 'negate' => false, 'operator' => 'equals', 'value' => 'a/b.php' ] ] ) ],
			'script regex containing a slash' => [ self::patch( [ [ 'kind' => 'script', 'negate' => false, 'operator' => 'regex', 'value' => '#a/b#' ] ] ) ],
			'regex that does not compile'     => [ self::patch( [ [ 'operator' => 'regex', 'value' => '#(#' ] + self::PATH ] ) ],
			'unsupported operator'            => [ self::patch( [ [ 'operator' => 'starts_with' ] + self::PATH ] ) ],
			'blank contains value'            => [ self::patch( [ [ 'operator' => 'contains', 'value' => ' ' ] + self::PATH ] ) ],
			'value too long'                  => [ self::patch( [ [ 'operator' => 'contains', 'value' => '/'.\str_repeat( 'a', 1024 ) ] + self::PATH ] ) ],
			'unsupported method'              => [ self::patch( [ self::PATH, [ 'kind' => 'method', 'negate' => false, 'value' => 'TRACE' ] ] ) ],
			'capability with a newline'      => [ self::patch( [ self::PATH, [ 'kind' => 'user_capability', 'negate' => false, 'capability' => "edit_posts
" ] ] ) ],
			'theme with a newline'            => [ self::patch( [ self::PATH, [ 'kind' => 'theme_active', 'negate' => false, 'theme' => "x
" ] ], [ 'targetExtension' => [ 'kind' => 'theme', 'slug' => 'x', 'affectedVersionRanges' => [ [ 'firstAffectedVersion' => '1', 'lastAffectedVersion' => '2' ] ] ] ] ) ],
			'REST namespace with a newline'   => [ self::patch( [ [ 'namespace' => "example/v1
" ] + self::REST ] ) ],
			'invalid capability'              => [ self::patch( [ self::PATH, [ 'kind' => 'user_capability', 'negate' => false, 'capability' => 'edit posts' ] ] ) ],
			'plugin file outside plugins'     => [ self::patch( [ self::PATH, [ 'kind' => 'plugin_active', 'negate' => false, 'pluginFile' => '../example.php' ] ] ) ],
			'invalid parameter source'        => [ self::patch( [ [ 'kind' => 'parameter_value', 'negate' => false, 'source' => 'cookie', 'name' => 'a', 'operator' => 'equals', 'value' => 'b' ] ] ) ],
		];
	}
}
