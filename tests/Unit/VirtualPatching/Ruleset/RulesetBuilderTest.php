<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Ruleset;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\{
	LocalCandidates,
	RulesetBuilder,
	RulesetFile,
	SiteState
};
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class RulesetBuilderTest extends TestCase {

	use PatchFixtures;

	public function testAffectedInstalledRuleBecomesARuleWithItsMode() :void {
		$patch = self::patch( [], [
			'publicVulnerabilityIds' => [
				[ 'authority' => 'CVE', 'identifier' => 'CVE-2026-0001' ],
				[ 'authority' => 'Wordfence', 'identifier' => 'CVE-2026-0001' ],
			],
		] );

		$result = ( new RulesetBuilder() )->build( [ self::entry( $patch, 'log' ) ], $this->site( '1.4.0' ), 1700000000, [ 'events' => '/cache/events.php' ] );

		$this->assertSame( [
			'events'         => '/cache/events.php',
			'format'         => RulesetFile::FORMAT,
			'generated_at'   => 1700000000,
			'active_plugins' => [ 'example/example.php' ],
			'active_themes'  => [ 'child', 'parent' ],
			'rules'          => [
				[
					'id'         => 'candidate-1',
					'mode'       => 'log',
					'advisories' => [ 'CVE-2026-0001' ],
					'conditions' => $patch[ 'virtualPatch' ][ 'matcher' ][ 'conditions' ],
				],
			],
		], $result[ 'ruleset' ] );
		$this->assertSame( [], $result[ 'rejected' ] );
		$this->assertSame( [], $result[ 'not_applicable' ] );
	}

	public function testUnaffectedOrMissingTargetsAreLeftOut() :void {
		$builder = new RulesetBuilder();

		$fixed = $builder->build( [ self::entry( self::patch() ) ], $this->site( '1.4.3' ), 0 );
		$this->assertSame( [], $fixed[ 'ruleset' ][ 'rules' ] );
		$this->assertArrayHasKey( 'candidate-1', $fixed[ 'not_applicable' ] );

		$missing = $builder->build( [ self::entry( self::patch() ) ], new SiteState( [], [], [ 'other/other.php' => '1.0' ], [] ), 0 );
		$this->assertSame( [], $missing[ 'ruleset' ][ 'rules' ] );
		$this->assertSame( 'not installed', $missing[ 'not_applicable' ][ 'candidate-1' ] );
	}

	public function testInactiveInstalledPluginKeepsItsRule() :void {
		$site = new SiteState( [], [], [ 'example/example.php' => '1.4.0' ], [] );
		$this->assertCount( 1, ( new RulesetBuilder() )->build( [ self::entry( self::patch() ) ], $site, 0 )[ 'ruleset' ][ 'rules' ] );
	}

	public function testDuplicateRulesAndUnknownModesAreRejected() :void {
		$other = self::patch( [], [ 'candidateRevisionId' => 'candidate-2' ] );

		$result = ( new RulesetBuilder() )->build(
			[ self::entry( self::patch() ), self::entry( self::patch() ), self::entry( $other, 'observe' ) ],
			$this->site( '1.4.0' ),
			0
		);

		$this->assertCount( 1, $result[ 'ruleset' ][ 'rules' ] );
		$this->assertSame( [ 'candidate-1' => 'duplicate rule id', 'candidate-2' => 'mode must be log or block' ], $result[ 'rejected' ] );
	}

	public function testThemeVersionIsReadByDirectory() :void {
		$patch = self::patch(
			[ [ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/' ] ],
			[ 'targetExtension' => [ 'kind' => 'theme', 'slug' => 'parent', 'affectedVersionRanges' => [ [ 'firstAffectedVersion' => '1', 'lastAffectedVersion' => '2' ] ] ] ]
		);
		$this->assertCount( 1, ( new RulesetBuilder() )->build( [ self::entry( $patch ) ], $this->site( '1.0' ), 0 )[ 'ruleset' ][ 'rules' ] );
	}

	public function testSingleFilePluginSlug() :void {
		$site = new SiteState( [], [], [ 'example.php' => '1.4.0' ], [] );
		$this->assertCount( 1, ( new RulesetBuilder() )->build( [ self::entry( self::patch() ) ], $site, 0 )[ 'ruleset' ][ 'rules' ] );
		$this->assertSame( [ 'plugin/example' => [ 'kind' => 'plugin', 'slug' => 'example' ] ], $site->installedAssets() );
	}

	public function testLocalCandidatesBecomeBlockRulesAndInvalidOnesAreRejected() :void {
		$invalid = self::patch( [], [ 'candidateRevisionId' => 'candidate-2', 'contractVersion' => 1 ] );

		$loaded = ( new LocalCandidates() )->load( (string)\json_encode( [ 'patches' => [ self::patch(), $invalid, 'not a patch' ] ] ) );

		$this->assertSame( [ self::entry( self::patch() ) ], $loaded[ 'rules' ] );
		$this->assertSame( [ 'candidate-2', '#2' ], \array_keys( $loaded[ 'rejected' ] ) );
	}

	private function site( string $pluginVersion ) :SiteState {
		return new SiteState(
			[ 'example/example.php' ],
			[ 'child', 'parent' ],
			[ 'example/example.php' => $pluginVersion, 'other/other.php' => '3.0' ],
			[ 'child' => '1.0', 'parent' => '1.5' ]
		);
	}

	/**
	 * A Candidate fixture as the builder receives a checked rule.
	 */
	private static function entry( array $patch, string $mode = 'block' ) :array {
		return [
			'kind' => $patch[ 'targetExtension' ][ 'kind' ],
			'slug' => $patch[ 'targetExtension' ][ 'slug' ],
			'mode' => $mode,
			'rule' => [
				'id'                     => $patch[ 'candidateRevisionId' ],
				'publicVulnerabilityIds' => $patch[ 'publicVulnerabilityIds' ],
				'affectedVersionRanges'  => $patch[ 'targetExtension' ][ 'affectedVersionRanges' ],
				'conditions'             => $patch[ 'virtualPatch' ][ 'matcher' ][ 'conditions' ],
			],
		];
	}
}
