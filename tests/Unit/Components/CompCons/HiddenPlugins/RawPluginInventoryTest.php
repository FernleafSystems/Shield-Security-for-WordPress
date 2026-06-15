<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\HiddenPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins\{
	PluginEntry,
	PluginType,
	RawPluginInventory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RawPluginInventoryTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testStandardInventoryMatchesWordpressEntryFileRules() :void {
		$pluginDir = $this->createTrackedTempDir( 'hidden-plugin-standard-' );
		$this->writeFile( $pluginDir.'/root-plugin.php', "<?php\n/*\nPlugin Name: Root Plugin\nVersion: 1.2.3\n*/\n" );
		$this->writeFile( $pluginDir.'/headerless.php', "<?php\nadd_action('init', 'x');\n" );
		$this->writeFile( $pluginDir.'/nested/plugin-main.php', "<?php\n/*\nPlugin Name: Nested Plugin\nVersion: 2.0.0\n*/\n" );
		$this->writeFile( $pluginDir.'/nested/deeper/ignored.php', "<?php\n/*\nPlugin Name: Ignored Plugin\n*/\n" );

		$entries = ( new RawPluginInventory( $pluginDir, '' ) )->scanStandardPlugins();
		$this->assertSame( [ 'nested/plugin-main.php', 'root-plugin.php' ], \array_map(
			static fn( PluginEntry $entry ) :string => $entry->file,
			$entries
		) );
		$this->assertSame( PluginType::Standard, $entries[ 0 ]->type );
		$this->assertSame( 'Nested Plugin', $entries[ 0 ]->name );
		$this->assertSame( '2.0.0', $entries[ 0 ]->version );
	}

	public function testMustUseInventoryIncludesTopLevelPhpWithoutHeaders() :void {
		$muDir = $this->createTrackedTempDir( 'hidden-plugin-mu-' );
		$this->writeFile( $muDir.'/aaa-loader.php', "<?php\nadd_action('init', 'x');\n" );
		$this->writeFile( $muDir.'/.hidden.php', "<?php\nadd_action('init', 'dotfile');\n" );
		$this->writeFile( $muDir.'/named.php', "<?php\n/*\nPlugin Name: Named MU\nVersion: 3.0.0\n*/\n" );
		$this->writeFile( $muDir.'/nested/ignored.php', "<?php\nadd_action('init', 'nested');\n" );
		$this->writeFile( $muDir.'/readme.txt', 'ignored' );

		$entries = ( new RawPluginInventory( '', $muDir ) )->scanMustUsePlugins();

		$this->assertSame( [ '.hidden.php', 'aaa-loader.php', 'named.php' ], \array_map(
			static fn( PluginEntry $entry ) :string => $entry->file,
			$entries
		) );
		$this->assertSame( PluginType::MustUse, $entries[ 0 ]->type );
		$this->assertSame( '.hidden.php', $entries[ 0 ]->name );
		$this->assertSame( 'aaa-loader.php', $entries[ 1 ]->name );
		$this->assertSame( 'Named MU', $entries[ 2 ]->name );
		$this->assertSame( '3.0.0', $entries[ 2 ]->version );
	}

	private function writeFile( string $path, string $content ) :void {
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			$this->assertTrue( \mkdir( $dir, 0777, true ) || \is_dir( $dir ) );
		}
		$this->assertNotFalse( \file_put_contents( $path, $content ) );
	}
}
