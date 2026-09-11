<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;

class WordPressPhpUnitCompatibilityPatcher {

	private const PATCH_MARKER = 'Shield PHPUnit 11 compatibility: WordPress-owned annotation reader.';

	public function patch( string $wpTestsDir ) :void {
		$path = Path::join( $wpTestsDir, 'includes', 'abstract-testcase.php' );
		if ( !\is_file( $path ) ) {
			throw new \RuntimeException( 'WordPress test base class is missing: '.$path );
		}

		$contents = \file_get_contents( $path );
		if ( !\is_string( $contents ) ) {
			throw new \RuntimeException( 'Failed to read WordPress test base class: '.$path );
		}
		if ( \str_contains( $contents, self::PATCH_MARKER ) ) {
			return;
		}

		if ( \preg_match( $this->legacyAnnotationReaderPattern(), $contents, $matches, \PREG_OFFSET_CAPTURE ) !== 1 ) {
			throw new \RuntimeException(
				'Unsupported WordPress test base class; unable to apply PHPUnit 11 compatibility patch: '.$path
			);
		}
		$matchedReader = $matches[ 0 ][ 0 ];
		$matchedOffset = $matches[ 0 ][ 1 ];
		$patched = \substr_replace( $contents, self::replacementAnnotationReader(), $matchedOffset, \strlen( $matchedReader ) );
		if ( \file_put_contents( $path, $patched ) === false ) {
			throw new \RuntimeException( 'Failed to write WordPress PHPUnit compatibility patch: '.$path );
		}
	}

	private function legacyAnnotationReaderPattern() :string {
		return '~\t\tif \( method_exists\( \$this, \'getAnnotations\' \) \) \{\R'
			.'\t\t\t// PHPUnit < 9\.5\.0\.\R'
			.'\t\t\t\$annotations = \$this->getAnnotations\(\);\R'
			.'\t\t\} else \{\R'
			.'\t\t\t// PHPUnit >= 9\.5\.0\.\R'
			.'\t\t\t\$annotations = \\\\PHPUnit\\\\Util\\\\Test::parseTestMethodAnnotations\(\R'
			.'\t\t\t\tstatic::class,\R'
			.'\t\t\t\t\$this->getName\( false \)\R'
			.'\t\t\t\);\R'
			.'\t\t\}~';
	}

	private static function replacementAnnotationReader() :string {
		return <<<'PHP'
		if ( method_exists( $this, 'getAnnotations' ) ) {
			// PHPUnit < 9.5.0.
			$annotations = $this->getAnnotations();
		} elseif ( method_exists( $this, 'name' ) ) {
			// Shield PHPUnit 11 compatibility: WordPress-owned annotation reader.
			$annotations = array(
				'class'  => array(),
				'method' => array(),
			);
			$reflections = array(
				'class'  => new ReflectionClass( static::class ),
				'method' => new ReflectionMethod( static::class, $this->name() ),
			);
			foreach ( $reflections as $depth => $reflection ) {
				$docblock = $reflection->getDocComment();
				foreach ( array( 'expectedDeprecated', 'expectedIncorrectUsage' ) as $annotation ) {
					if ( preg_match_all( '/@' . $annotation . '\s+([^\s*]+)/', (string) $docblock, $matches ) ) {
						$annotations[ $depth ][ $annotation ] = $matches[ 1 ];
					}
				}
			}
		} else {
			// PHPUnit >= 9.5.0 and < 11.0.
			$annotations = \PHPUnit\Util\Test::parseTestMethodAnnotations(
				static::class,
				$this->getName( false )
			);
		}
PHP;
	}
}
