<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Architecture;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class PluginControllerConContractTest extends TestCase {

	use PluginPathsTrait;

	public function testIntegrationClassesCallingConExposeStaticConMethod() :void {
		$this->ensureExternalBaseClassStubs();

		$classes = $this->findIntegrationClassesCallingCon();

		$this->assertNotEmpty(
			$classes,
			'Expected at least one Integrations class to call self::con()/static::con().'
		);

		foreach ( $classes as $class => $file ) {
			$this->assertTrue(
				\class_exists( $class ),
				\sprintf( "Failed loading class '%s' from '%s'.", $class, $file )
			);

			$reflection = new \ReflectionClass( $class );
			$this->assertTrue(
				$reflection->hasMethod( 'con' ),
				\sprintf(
					"Class '%s' calls con() but has no con() method (likely missing PluginControllerConsumer inheritance/trait). File: %s",
					$class,
					$file
				)
			);
			$this->assertTrue(
				$reflection->getMethod( 'con' )->isStatic(),
				\sprintf( "Class '%s' should expose a static con() method. File: %s", $class, $file )
			);
		}
	}

	private function ensureExternalBaseClassStubs() :void {
		if ( !\class_exists( 'NF_Abstracts_Action', false ) ) {
			eval( 'class NF_Abstracts_Action { public function __construct() {} }' );
		}
	}

	/**
	 * @return array<string, string> Class => relative file path
	 */
	private function findIntegrationClassesCallingCon() :array {
		$root = $this->getPluginFilePath( 'src/Modules/Integrations' );
		$this->assertDirectoryExists( $root, 'Integrations source directory should exist.' );

		$classes = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
		);

		/** @var \SplFileInfo $file */
		foreach ( $iterator as $file ) {
			if ( !$file->isFile() || $file->getExtension() !== 'php' ) {
				continue;
			}

			$source = (string)\file_get_contents( $file->getPathname() );
			if ( $source === '' || \preg_match( '/\b(?:self|static)::con\s*\(/', $source ) !== 1 ) {
				continue;
			}

			$class = $this->extractClassName( $source );
			if ( empty( $class ) ) {
				continue;
			}

			$classes[ $class ] = $this->normalizePath( $file->getPathname() );
		}

		\ksort( $classes );
		return $classes;
	}

	private function extractClassName( string $source ) :string {
		$matches = [];
		$found = \preg_match(
			'/\bnamespace\s+([^;]+);\s+.*?\b(?:abstract\s+|final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/s',
			$source,
			$matches
		) === 1;

		return $found ? \sprintf( '%s\\%s', \trim( $matches[ 1 ] ), \trim( $matches[ 2 ] ) ) : '';
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}
