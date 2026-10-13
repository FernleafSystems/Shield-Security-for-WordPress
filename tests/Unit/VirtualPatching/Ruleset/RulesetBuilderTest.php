<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Ruleset;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\{
	RulesetBuilder,
	SiteState
};
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class RulesetBuilderTest extends TestCase {

	use PatchFixtures;

	public function testAffectedInstalledPatchBecomesARule() :void {
		$patch = self::patch( [], [
			'publicVulnerabilityIds' => [
				[ 'authority' => 'CVE', 'identifier' => 'CVE-2026-0001' ],
				[ 'authority' => 'Wordfence', 'identifier' => 'CVE-2026-0001' ],
			],
		] );

		$result = ( new RulesetBuilder() )->build( [ $patch ], $this->site( '1.4.0' ), 1700000000, [ 'events' => '/cache/events.php' ] );

		$this->assertSame( [
			'events'         => '/cache/events.php',
			'format'         => 1,
			'generated_at'   => 1700000000,
			'active_plugins' => [ 'example/example.php' ],
			'active_themes'  => [ 'child', 'parent' ],
			'rules'          => [
				[
					'id'         => 'candidate-1',
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

		$fixed = $builder->build( [ self::patch() ], $this->site( '1.4.3' ), 0 );
		$this->assertSame( [], $fixed[ 'ruleset' ][ 'rules' ] );
		$this->assertArrayHasKey( 'candidate-1', $fixed[ 'not_applicable' ] );

		$missing = $builder->build( [ self::patch() ], new SiteState( [], [], [ 'other/other.php' => '1.0' ], [] ), 0 );
		$this->assertSame( [], $missing[ 'ruleset' ][ 'rules' ] );
		$this->assertSame( 'not installed', $missing[ 'not_applicable' ][ 'candidate-1' ] );
	}

	public function testInactiveInstalledPluginKeepsItsRule() :void {
		$site = new SiteState( [], [], [ 'example/example.php' => '1.4.0' ], [] );
		$this->assertCount( 1, ( new RulesetBuilder() )->build( [ self::patch() ], $site, 0 )[ 'ruleset' ][ 'rules' ] );
	}

	public function testInvalidAndDuplicatePatchesAreRejected() :void {
		$invalid = self::patch( [], [ 'candidateRevisionId' => 'candidate-2', 'contractVersion' => 1 ] );

		$result = ( new RulesetBuilder() )->build( [ self::patch(), $invalid, self::patch(), 'not a patch' ], $this->site( '1.4.0' ), 0 );

		$this->assertCount( 1, $result[ 'ruleset' ][ 'rules' ] );
		$this->assertSame( [ 'candidate-2', 'candidate-1', '#3' ], \array_keys( $result[ 'rejected' ] ) );
	}

	public function testThemeVersionIsReadByDirectory() :void {
		$patch = self::patch(
			[ [ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/' ] ],
			[ 'targetExtension' => [ 'kind' => 'theme', 'slug' => 'parent', 'affectedVersionRanges' => [ [ 'firstAffectedVersion' => '1', 'lastAffectedVersion' => '2' ] ] ] ]
		);
		$this->assertCount( 1, ( new RulesetBuilder() )->build( [ $patch ], $this->site( '1.0' ), 0 )[ 'ruleset' ][ 'rules' ] );
	}

	public function testSingleFilePluginSlug() :void {
		$site = new SiteState( [], [], [ 'example.php' => '1.4.0' ], [] );
		$this->assertCount( 1, ( new RulesetBuilder() )->build( [ self::patch() ], $site, 0 )[ 'ruleset' ][ 'rules' ] );
	}

	private function site( string $pluginVersion ) :SiteState {
		return new SiteState(
			[ 'example/example.php' ],
			[ 'child', 'parent' ],
			[ 'example/example.php' => $pluginVersion, 'other/other.php' => '3.0' ],
			[ 'child' => '1.0', 'parent' => '1.5' ]
		);
	}
}
