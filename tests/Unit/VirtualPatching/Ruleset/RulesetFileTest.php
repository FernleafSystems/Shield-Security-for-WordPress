<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Ruleset;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\RulesetFile;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class RulesetFileTest extends TestCase {

	use TempDirLifecycleTrait;

	public const RULESET = [
		'format'         => RulesetFile::FORMAT,
		'generated_at'   => 1700000000,
		'active_plugins' => [ 'example/example.php' ],
		'active_themes'  => [ 'theme' ],
		'rules'          => [
			[
				'id'         => 'candidate-1',
				'mode'       => 'block',
				'advisories' => [ 'CVE-2026-0001' ],
				'conditions' => [
					[ 'kind' => 'path', 'negate' => false, 'operator' => 'regex', 'value' => "#^/it's/\\d+\$#" ],
				],
			],
		],
	];

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRenderedRulesetReadsBackWithItsHash() :void {
		$path = $this->writeFile( RulesetFile::render( self::RULESET ) );
		$this->assertSame( self::RULESET + [ 'hash' => RulesetFile::hash( self::RULESET ) ], RulesetFile::read( $path ) );
	}

	public function testTamperedRulesetIsIgnored() :void {
		$path = $this->writeFile( \str_replace( 'candidate-1', 'candidate-2', RulesetFile::render( self::RULESET ) ) );
		$this->assertNull( RulesetFile::read( $path ) );
	}

	public function testOtherFormatIsIgnored() :void {
		foreach ( [ 1, RulesetFile::FORMAT + 1 ] as $format ) {
			$otherFormat = self::RULESET;
			$otherFormat[ 'format' ] = $format;
			$this->assertNull( RulesetFile::read( $this->writeFile( RulesetFile::render( $otherFormat ) ) ), (string)$format );
		}
	}

	public function testAnyFormatCanBeReadWhenAsked() :void {
		$previous = self::RULESET;
		$previous[ 'format' ] = 1;
		$path = $this->writeFile( RulesetFile::render( $previous ) );
		$this->assertSame( 1, RulesetFile::read( $path, null )[ 'format' ] ?? null );
		$this->assertNull( RulesetFile::read( $this->writeFile( \str_replace( 'candidate-1', 'x', RulesetFile::render( $previous ) ) ), null ) );
	}

	public function testUnreadableOrMissingFileIsIgnored() :void {
		$this->assertNull( RulesetFile::read( $this->writeFile( '<?php return [' ) ) );
		$this->assertNull( RulesetFile::read( $this->createTrackedTempDir().'/missing.php' ) );
	}

	private function writeFile( string $content ) :string {
		$path = $this->createTrackedTempDir().'/ruleset.php';
		\file_put_contents( $path, $content );
		return $path;
	}
}
