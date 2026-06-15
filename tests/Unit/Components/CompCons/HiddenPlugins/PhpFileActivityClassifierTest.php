<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\HiddenPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins\{
	PhpFileActivity,
	PhpFileActivityClassifier
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PhpFileActivityClassifierTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	/**
	 * @dataProvider provideInertPhpPayloads
	 */
	public function testClassifiesInertPhpPayloads( string $content ) :void {
		$this->assertSame(
			PhpFileActivity::Inert,
			( new PhpFileActivityClassifier() )->classify( $this->phpFile( $content ) )
		);
	}

	public static function provideInertPhpPayloads() :array {
		return [
			'empty'        => [ '' ],
			'comment only' => [ "<?php\n// Silence is golden.\n" ],
			'docblock'     => [ "<?php\n/** Plugin Name: Placeholder */\n" ],
			'declare only' => [ "<?php declare(strict_types=1);\n" ],
			'utf8 bom'     => [ "\xEF\xBB\xBF<?php\n// noop\n" ],
		];
	}

	public function testClassifiesRuntimePhpAsExecutable() :void {
		$this->assertSame(
			PhpFileActivity::Executable,
			( new PhpFileActivityClassifier() )->classify( $this->phpFile( "<?php\nadd_action('init', 'hidden_shell');\n" ) )
		);
	}

	public function testExecutableIndexPhpIsNotSuppressedByFilename() :void {
		$this->assertSame(
			PhpFileActivity::Executable,
			( new PhpFileActivityClassifier() )->classify( $this->phpFile( "<?php\nrequire __DIR__.'/payload.php';\n", 'index.php' ) )
		);
	}

	public function testMissingFileIsAlertableAsUnreadable() :void {
		$this->assertSame(
			PhpFileActivity::Unreadable,
			( new PhpFileActivityClassifier() )->classify( $this->createTrackedTempPath( 'missing-', '.php' ) )
		);
	}

	public function testInvalidPhpIsAlertable() :void {
		$this->assertSame(
			PhpFileActivity::Invalid,
			( new PhpFileActivityClassifier() )->classify( $this->phpFile( "<?php\nfunction broken(\n" ) )
		);
	}

	public function testRejectsUnsupportedActivity() :void {
		$this->expectException( \InvalidArgumentException::class );

		PhpFileActivity::isAlertable( 'unsupported-activity' );
	}

	private function phpFile( string $content, string $name = 'fixture.php' ) :string {
		$dir = $this->createTrackedTempDir( 'hidden-plugin-classifier-' );
		$path = $dir.'/'.$name;
		$this->assertNotFalse( \file_put_contents( $path, $content ) );
		return $path;
	}
}
