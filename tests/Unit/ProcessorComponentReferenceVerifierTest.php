<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\ProcessorComponentReferenceVerifier;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempPathJoinTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ProcessorComponentReferenceVerifierTest extends TestCase {

	use TempDirLifecycleTrait;
	use TempPathJoinTrait;

	private Filesystem $fs;

	private string $tempDir;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = $this->createTrackedTempDir( 'shield-component-verifier-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testProcessorReferencesAreSatisfiedByComponentLoaderMap() :void {
		$processor = $this->writePhp( 'src/Modules/Plugin/Processor.php', <<<'PHP'
<?php
$components->license->execute();
self::con()->comps->hidden_plugins->detect();
add_action( 'init', fn() => self::con()->comps->forms_users->execute() );
PHP );
		$loader = $this->writeLoaderMap( [
			'forms_users',
			'hidden_plugins',
			'license',
		] );

		$this->assertSame( [], $this->verifier()->findMissingComponentKeys( $processor, $loader ) );
	}

	public function testMissingProcessorReferenceIsReported() :void {
		$processor = $this->writePhp( 'src/Modules/Plugin/Processor.php', <<<'PHP'
<?php
$components->license->execute();
$components->hidden_plugins->execute();
PHP );
		$loader = $this->writeLoaderMap( [ 'license' ] );

		$this->assertSame(
			[ 'hidden_plugins' ],
			$this->verifier()->findMissingComponentKeys( $processor, $loader )
		);
	}

	public function testExecuteComponentKeysAreExtractedSeparatelyFromOtherComponentAccess() :void {
		$processor = $this->writePhp( 'src/Modules/Plugin/Processor.php', <<<'PHP'
<?php
$components->license->execute();
self::con()->comps->hidden_plugins->detect();
add_action( 'init', fn() => self::con()->comps->forms_users->execute() );
PHP );

		$this->assertSame(
			[ 'forms_users', 'license' ],
			$this->verifier()->extractProcessorExecuteComponentKeys( $processor )
		);
	}

	public function testCommentedReferencesAreIgnored() :void {
		$processor = $this->writePhp( 'src/Modules/Plugin/Processor.php', <<<'PHP'
<?php
// $components->commented_missing->execute();
$components->license->execute();
PHP );
		$loader = $this->writeLoaderMap( [ 'license' ] );

		$this->assertSame( [], $this->verifier()->findMissingComponentKeys( $processor, $loader ) );
	}

	public function testMappedComponentClassFilesResolveThroughComponentLoaderImports() :void {
		$loader = $this->writeNamespacedComponentLoader( [
			'hidden_plugins'    => 'CompCons\CloakedPluginsCon',
			'mainwp'            => 'MainwpCon',
			'security_profiles' => 'Profiles\SecurityProfilesCon',
		] );
		$this->writePhp( 'src/Components/CompCons/CloakedPluginsCon.php', '<?php' );
		$this->writePhp( 'src/Modules/Integrations/Lib/MainWP/Controller.php', '<?php' );
		$this->writePhp( 'src/Profiles/SecurityProfilesCon.php', '<?php' );

		$this->assertSame(
			[],
			$this->verifier()->findMissingComponentClassFiles( $loader, $this->tempDir )
		);
	}

	public function testMissingMappedComponentClassFileIsReported() :void {
		$loader = $this->writeNamespacedComponentLoader( [
			'hidden_plugins' => 'CompCons\CloakedPluginsCon',
		] );

		$this->assertSame(
			[
				[
					'key'   => 'hidden_plugins',
					'class' => 'FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPluginsCon',
					'path'  => 'src/Components/CompCons/CloakedPluginsCon.php',
				],
			],
			$this->verifier()->findMissingComponentClassFiles( $loader, $this->tempDir )
		);
	}

	private function verifier() :ProcessorComponentReferenceVerifier {
		return new ProcessorComponentReferenceVerifier();
	}

	private function writePhp( string $relativePath, string $content ) :string {
		$path = $this->tempPath( $relativePath );
		$this->fs->dumpFile( $path, $content );
		return $path;
	}

	/**
	 * @param list<string> $keys
	 */
	private function writeLoaderMap( array $keys ) :string {
		$entries = \implode( "\n", \array_map(
			static fn( string $key ) :string => \sprintf( "\t\t\t'%s' => %s::class,", $key, \ucfirst( $key ) ),
			$keys
		) );

		return $this->writePhp( 'src/Components/ComponentLoader.php', <<<PHP
<?php
class ComponentLoader {
	private function getConsMap() :array {
		return [
{$entries}
		];
	}
}
PHP );
	}

	/**
	 * @param array<string,string> $entries
	 */
	private function writeNamespacedComponentLoader( array $entries ) :string {
		$map = \implode( "\n", \array_map(
			static fn( string $key, string $class ) :string => \sprintf( "\t\t\t'%s' => %s::class,", $key, $class ),
			\array_keys( $entries ),
			$entries
		) );

		return $this->writePhp( 'src/Components/ComponentLoader.php', <<<PHP
<?php
namespace FernleafSystems\Wordpress\Plugin\Shield\Components;

use FernleafSystems\Wordpress\Plugin\Shield\{
	Profiles,
	Zones
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller as MainwpCon;

class ComponentLoader {
	private function getConsMap() :array {
		return [
{$map}
		];
	}
}
PHP );
	}
}
