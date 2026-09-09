<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\ProcessorComponentReferenceVerifier;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\TwigTemplateReferenceVerifier;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use PHPUnit\Framework\TestCase;

class SourcePackageCoherenceTest extends TestCase {

	use PluginPathsTrait;

	public function testSourceTwigStaticReferencesResolve() :void {
		$templateRoot = $this->getPluginFilePath( 'templates/twig' );
		$this->assertDirectoryExists( $templateRoot );

		$verifier = new TwigTemplateReferenceVerifier();
		$missingReferences = $verifier->findMissingReferences( $templateRoot );

		$this->assertSame(
			[],
			$missingReferences,
			'Source Twig static references should resolve: '.$verifier->formatMissingReferences( $missingReferences )
		);
	}

	public function testSourceProcessorComponentReferencesAreMapped() :void {
		$processor = $this->getPluginFilePath( 'src/Modules/Plugin/Processor.php' );
		$componentLoader = $this->getPluginFilePath( 'src/Components/ComponentLoader.php' );
		$this->assertFileExistsWithDebug( $processor, 'Source should include Processor.php.' );
		$this->assertFileExistsWithDebug( $componentLoader, 'Source should include ComponentLoader.php.' );

		$verifier = new ProcessorComponentReferenceVerifier();
		$missingKeys = $verifier->findMissingComponentKeys( $processor, $componentLoader );

		$this->assertSame(
			[],
			$missingKeys,
			'Source Processor component references should be mapped: '.$verifier->formatMissingKeys( $missingKeys )
		);
	}

	public function testSourceComponentLoaderMappedClassFilesExist() :void {
		$componentLoader = $this->getPluginFilePath( 'src/Components/ComponentLoader.php' );
		$this->assertFileExistsWithDebug( $componentLoader, 'Source should include ComponentLoader.php.' );

		$verifier = new ProcessorComponentReferenceVerifier();
		$missingClassFiles = $verifier->findMissingComponentClassFiles( $componentLoader, $this->getPluginRoot() );

		$this->assertSame(
			[],
			$missingClassFiles,
			'Source ComponentLoader mapped class files should exist: '.$verifier->formatMissingClassFiles( $missingClassFiles )
		);
	}
}
