<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\WordPressPhpUnitCompatibilityPatcher;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class WordPressPhpUnitCompatibilityPatcherTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_patches_the_legacy_wordpress_annotation_reader_once() :void {
		$wpTestsDir = $this->createTrackedTempDir( 'shield-wp-phpunit-patch-' );
		$path = Path::join( $wpTestsDir, 'includes', 'abstract-testcase.php' );
		\mkdir( \dirname( $path ), 0777, true );
		\file_put_contents( $path, $this->legacyFixture() );

		$patcher = new WordPressPhpUnitCompatibilityPatcher();
		$patcher->patch( $wpTestsDir );
		$patched = (string)\file_get_contents( $path );
		$patcher->patch( $wpTestsDir );

		$this->assertStringContainsString( 'Shield PHPUnit 11 compatibility: WordPress-owned annotation reader.', $patched );
		$this->assertStringContainsString( "new ReflectionMethod( static::class, \$this->name() )", $patched );
		$this->assertStringContainsString( "'expectedDeprecated', 'expectedIncorrectUsage'", $patched );
		$this->assertSame( 1, \substr_count( (string)\file_get_contents( $path ), 'Shield PHPUnit 11 compatibility:' ) );
	}

	public function test_removes_group_metadata_from_check_requirements_prose_on_patched_base() :void {
		$wpTestsDir = $this->createTrackedTempDir( 'shield-wp-phpunit-patch-prose-' );
		$path = Path::join( $wpTestsDir, 'includes', 'abstract-testcase.php' );
		\mkdir( \dirname( $path ), 0777, true );
		$patcher = new WordPressPhpUnitCompatibilityPatcher();
		\file_put_contents( $path, $this->legacyFixture() );
		$patcher->patch( $wpTestsDir );
		\file_put_contents( $path, \str_replace(
			"abstract class WP_UnitTestCase_Base {\n",
			"abstract class WP_UnitTestCase_Base {\n".$this->checkRequirementsFixture(),
			(string)\file_get_contents( $path )
		) );

		$patcher->patch( $wpTestsDir );
		$patched = (string)\file_get_contents( $path );
		$patcher->patch( $wpTestsDir );

		$this->assertStringNotContainsString( '@group', $patched );
		$this->assertStringContainsString( 'protected function checkRequirements()', $patched );
		$this->assertSame( 1, \substr_count( $patched, 'Shield PHPUnit 11 compatibility:' ) );
		$this->assertSame( $patched, (string)\file_get_contents( $path ) );
	}

	public function test_rejects_an_unrecognised_wordpress_test_base() :void {
		$wpTestsDir = $this->createTrackedTempDir( 'shield-wp-phpunit-patch-invalid-' );
		$path = Path::join( $wpTestsDir, 'includes', 'abstract-testcase.php' );
		\mkdir( \dirname( $path ), 0777, true );
		\file_put_contents( $path, "<?php\nclass WP_UnitTestCase_Base {}\n" );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Unsupported WordPress test base class' );

		( new WordPressPhpUnitCompatibilityPatcher() )->patch( $wpTestsDir );
	}

	private function legacyFixture() :string {
		return <<<'PHP'
<?php
abstract class WP_UnitTestCase_Base {
	public function expectDeprecated() {
		if ( method_exists( $this, 'getAnnotations' ) ) {
			// PHPUnit < 9.5.0.
			$annotations = $this->getAnnotations();
		} else {
			// PHPUnit >= 9.5.0.
			$annotations = \PHPUnit\Util\Test::parseTestMethodAnnotations(
				static::class,
				$this->getName( false )
			);
		}
	}
}
PHP;
	}

	private function checkRequirementsFixture() :string {
		return <<<'PHP'
	/**
	 * Allows tests to be skipped on single or multisite installs by using @group annotations.
	 *
	 * @deprecated 5.9.0 This method has not been functional since PHPUnit 7.0.
	 */
	protected function checkRequirements() {
	}

PHP;
	}
}
