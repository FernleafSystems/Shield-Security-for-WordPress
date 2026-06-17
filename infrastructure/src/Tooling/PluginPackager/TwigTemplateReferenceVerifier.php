<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

class TwigTemplateReferenceVerifier {

	/**
	 * @return list<array{source:string,line:int,reference:string,target:string}>
	 */
	public function findMissingReferences( string $templateRoot ) :array {
		if ( !\is_dir( $templateRoot ) ) {
			throw new \InvalidArgumentException( \sprintf( 'Twig template root does not exist: %s', $templateRoot ) );
		}

		$missing = [];
		foreach ( $this->enumTwigFiles( $templateRoot ) as $templatePath ) {
			$source = $this->relativePath( $templatePath, $templateRoot );
			foreach ( $this->extractStaticReferences( $templatePath ) as $reference ) {
				$targetPath = $this->resolveReferencePath( $reference[ 'reference' ], $templatePath, $templateRoot );
				if ( !\is_file( $targetPath ) ) {
					$missing[] = [
						'source'    => $source,
						'line'      => $reference[ 'line' ],
						'reference' => $reference[ 'reference' ],
						'target'    => $this->relativePath( $targetPath, $templateRoot ),
					];
				}
			}
		}

		return $missing;
	}

	/**
	 * @param list<array{source:string,line:int,reference:string,target:string}> $missing
	 */
	public function formatMissingReferences( array $missing ) :string {
		return \implode( ', ', \array_map(
			static fn( array $ref ) :string => \sprintf(
				'%s:%d -> %s',
				$ref[ 'source' ],
				$ref[ 'line' ],
				$ref[ 'reference' ]
			),
			$missing
		) );
	}

	/**
	 * @return list<string>
	 */
	private function enumTwigFiles( string $templateRoot ) :array {
		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $templateRoot, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isFile() && $item->getExtension() === 'twig' ) {
				$files[] = $item->getPathname();
			}
		}
		\sort( $files );
		return $files;
	}

	/**
	 * @return list<array{line:int,reference:string}>
	 */
	private function extractStaticReferences( string $templatePath ) :array {
		$content = \file_get_contents( $templatePath );
		if ( $content === false ) {
			throw new \RuntimeException( \sprintf( 'Unable to read Twig template: %s', $templatePath ) );
		}

		$references = [];
		if ( !\preg_match_all(
			'#\{%-?\s*(include|extends|embed|import|from)\s+(.*?)-?%\}#s',
			$content,
			$matches,
			\PREG_SET_ORDER | \PREG_OFFSET_CAPTURE
		) ) {
			return $references;
		}

		foreach ( $matches as $match ) {
			$reference = $this->extractRequiredStaticReference(
				(string)$match[ 1 ][ 0 ],
				(string)$match[ 2 ][ 0 ]
			);
			if ( $reference === null ) {
				continue;
			}

			$references[] = [
				'line'      => $this->lineNumberForOffset( $content, (int)$match[ 0 ][ 1 ] ),
				'reference' => $reference,
			];
		}

		return $references;
	}

	private function extractRequiredStaticReference( string $tagName, string $tagBody ) :?string {
		if ( !\preg_match( '#^\s*([\'"])([^\'"]+\.twig)\1(?P<tail>.*)$#s', $tagBody, $match ) ) {
			return null;
		}

		$tail = \trim( (string)$match[ 'tail' ] );
		if ( \preg_match( '#\bignore\s+missing\b#s', $tail ) === 1 ) {
			return null;
		}
		if ( !$this->isSupportedStaticReferenceTail( $tagName, $tail ) ) {
			return null;
		}

		return $match[ 2 ];
	}

	private function isSupportedStaticReferenceTail( string $tagName, string $tail ) :bool {
		if ( $tail === '' ) {
			return true;
		}

		if ( $tagName === 'include' || $tagName === 'embed' ) {
			return \preg_match( '#^(?:with\b|only\b)#s', $tail ) === 1;
		}
		if ( $tagName === 'import' ) {
			return \preg_match( '#^as\b#s', $tail ) === 1;
		}
		if ( $tagName === 'from' ) {
			return \preg_match( '#^import\b#s', $tail ) === 1;
		}

		return false;
	}

	private function lineNumberForOffset( string $content, int $offset ) :int {
		return \substr_count( \substr( $content, 0, $offset ), "\n" ) + 1;
	}

	private function resolveReferencePath( string $reference, string $templatePath, string $templateRoot ) :string {
		if ( \strpos( $reference, '/' ) === 0 ) {
			return Path::join( $templateRoot, \ltrim( $reference, '/' ) );
		}

		return Path::join( \dirname( $templatePath ), $reference );
	}

	private function relativePath( string $path, string $root ) :string {
		return \str_replace( '\\', '/', Path::makeRelative( Path::normalize( $path ), Path::normalize( $root ) ) );
	}
}
