<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

class ProcessorComponentReferenceVerifier {

	private const SHIELD_NAMESPACE = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\';

	/**
	 * @return list<string>
	 */
	public function findMissingComponentKeys( string $processorPath, string $componentLoaderPath ) :array {
		$processorKeys = $this->extractProcessorComponentKeys( $processorPath );
		$mapKeys = $this->extractComponentLoaderMapKeys( $componentLoaderPath );

		return \array_values( \array_diff( $processorKeys, $mapKeys ) );
	}

	/**
	 * @return list<array{key:string,class:string,path:string}>
	 */
	public function findMissingComponentClassFiles( string $componentLoaderPath, string $pluginRoot ) :array {
		$code = $this->readPhpWithoutComments( $componentLoaderPath );
		$namespace = $this->extractNamespace( $code );
		$imports = $this->extractUseImports( $code );

		$missing = [];
		foreach ( $this->extractComponentLoaderMapClassExpressionsFromCode( $code ) as $key => $classExpression ) {
			$class = $this->resolveClassExpression( $classExpression, $namespace, $imports );
			$path = $this->classFilePathForPluginClass( $class, $pluginRoot );
			if ( $path !== null && !\is_file( $path ) ) {
				$missing[] = [
					'key'   => $key,
					'class' => $class,
					'path'  => $this->relativePluginPath( $path, $pluginRoot ),
				];
			}
		}

		return $missing;
	}

	/**
	 * @return list<string>
	 */
	public function extractProcessorComponentKeys( string $processorPath ) :array {
		$code = $this->readPhpWithoutComments( $processorPath );
		$keys = [];
		foreach ( [
			'#\$components\s*->\s*([A-Za-z_][A-Za-z0-9_]*)#',
			'#(?:self\s*::\s*con\s*\(\s*\)|\$con)\s*->\s*comps\s*->\s*([A-Za-z_][A-Za-z0-9_]*)#',
		] as $pattern ) {
			if ( \preg_match_all( $pattern, $code, $matches ) ) {
				foreach ( $matches[ 1 ] as $key ) {
					$keys[] = $key;
				}
			}
		}

		return $this->uniqueSorted( $keys );
	}

	/**
	 * @return list<string>
	 */
	public function extractProcessorExecuteComponentKeys( string $processorPath ) :array {
		$code = $this->readPhpWithoutComments( $processorPath );
		$keys = [];
		foreach ( [
			'#\$components\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\s*->\s*execute\s*\(#',
			'#(?:self\s*::\s*con\s*\(\s*\)|\$con)\s*->\s*comps\s*->\s*([A-Za-z_][A-Za-z0-9_]*)\s*->\s*execute\s*\(#',
		] as $pattern ) {
			if ( \preg_match_all( $pattern, $code, $matches ) ) {
				foreach ( $matches[ 1 ] as $key ) {
					$keys[] = $key;
				}
			}
		}

		return $this->uniqueSorted( $keys );
	}

	/**
	 * @return list<string>
	 */
	public function extractComponentLoaderMapKeys( string $componentLoaderPath ) :array {
		return \array_keys( $this->extractComponentLoaderMapClassExpressions( $componentLoaderPath ) );
	}

	/**
	 * @return array<string,string>
	 */
	public function extractComponentLoaderMapClassExpressions( string $componentLoaderPath ) :array {
		return $this->extractComponentLoaderMapClassExpressionsFromCode( $this->readPhpWithoutComments( $componentLoaderPath ) );
	}

	/**
	 * @param list<string> $keys
	 */
	public function formatMissingKeys( array $keys ) :string {
		return \implode( ', ', $keys );
	}

	/**
	 * @param list<array{key:string,class:string,path:string}> $missing
	 */
	public function formatMissingClassFiles( array $missing ) :string {
		return \implode( ', ', \array_map(
			static fn( array $item ) :string => \sprintf(
				'%s -> %s (%s)',
				$item[ 'key' ],
				$item[ 'class' ],
				$item[ 'path' ]
			),
			$missing
		) );
	}

	private function readPhpWithoutComments( string $path ) :string {
		if ( !\is_file( $path ) ) {
			throw new \InvalidArgumentException( \sprintf( 'PHP source file does not exist: %s', $path ) );
		}
		$content = \file_get_contents( $path );
		if ( $content === false ) {
			throw new \RuntimeException( \sprintf( 'Unable to read PHP source file: %s', $path ) );
		}

		return \implode( '', \array_map(
			static function ( $token ) :string {
				if ( \is_string( $token ) ) {
					return $token;
				}
				return \in_array( $token[ 0 ], [ \T_COMMENT, \T_DOC_COMMENT ], true ) ? '' : $token[ 1 ];
			},
			\token_get_all( $content )
		) );
	}

	/**
	 * @return array<string,string>
	 */
	private function extractComponentLoaderMapClassExpressionsFromCode( string $code ) :array {
		$body = $this->extractGetConsMapBody( $code );
		if ( !\preg_match_all(
			'#([\'"])([A-Za-z_][A-Za-z0-9_]*)\1\s*=>\s*(\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\s*::\s*class#',
			$body,
			$matches,
			\PREG_SET_ORDER
		) ) {
			return [];
		}

		$map = [];
		foreach ( $matches as $match ) {
			$map[ $match[ 2 ] ] = $match[ 3 ];
		}
		\ksort( $map );

		return $map;
	}

	private function extractGetConsMapBody( string $code ) :string {
		if ( !\preg_match( '#function\s+getConsMap\s*\([^)]*\)\s*:[^{]+{#', $code, $match, \PREG_OFFSET_CAPTURE ) ) {
			return '';
		}

		$start = (int)$match[ 0 ][ 1 ] + \strlen( $match[ 0 ][ 0 ] );
		$depth = 1;
		$length = \strlen( $code );
		for ( $i = $start; $i < $length; $i++ ) {
			if ( $code[ $i ] === '{' ) {
				$depth++;
			}
			elseif ( $code[ $i ] === '}' ) {
				$depth--;
				if ( $depth === 0 ) {
					return \substr( $code, $start, $i - $start );
				}
			}
		}

		return '';
	}

	private function extractNamespace( string $code ) :string {
		if ( \preg_match( '#\bnamespace\s+([^;{]+)\s*;#', $code, $match ) !== 1 ) {
			return '';
		}

		return \trim( $match[ 1 ], " \t\n\r\0\x0B\\" );
	}

	/**
	 * @return array<string,string>
	 */
	private function extractUseImports( string $code ) :array {
		$header = $this->extractFileHeader( $code );
		if ( !\preg_match_all( '#\buse\s+([^;]+);#s', $header, $matches ) ) {
			return [];
		}

		$imports = [];
		foreach ( $matches[ 1 ] as $statement ) {
			foreach ( $this->parseUseStatement( $statement ) as $alias => $class ) {
				$imports[ $alias ] = $class;
			}
		}

		return $imports;
	}

	private function extractFileHeader( string $code ) :string {
		if ( \preg_match( '#\bclass\s+ComponentLoader\b#', $code, $match, \PREG_OFFSET_CAPTURE ) !== 1 ) {
			return $code;
		}

		return \substr( $code, 0, (int)$match[ 0 ][ 1 ] );
	}

	/**
	 * @return array<string,string>
	 */
	private function parseUseStatement( string $statement ) :array {
		$statement = \trim( $statement );
		if ( \preg_match( '#^(?:function|const)\s+#i', $statement ) === 1 ) {
			return [];
		}

		if ( \strpos( $statement, '{' ) !== false ) {
			return $this->parseGroupedUseStatement( $statement );
		}

		$imports = [];
		foreach ( \explode( ',', $statement ) as $class ) {
			$this->addUseImport( $imports, $class );
		}

		return $imports;
	}

	/**
	 * @return array<string,string>
	 */
	private function parseGroupedUseStatement( string $statement ) :array {
		if ( \preg_match( '#^(.+?)\\\\\s*\{\s*(.+?)\s*\}$#s', $statement, $match ) !== 1 ) {
			return [];
		}

		$prefix = \trim( $match[ 1 ], " \t\n\r\0\x0B\\" );
		$imports = [];
		foreach ( \explode( ',', $match[ 2 ] ) as $class ) {
			$class = \trim( $class );
			if ( $class === '' ) {
				continue;
			}
			$this->addUseImport( $imports, $prefix.'\\'.$class );
		}

		return $imports;
	}

	/**
	 * @param array<string,string> $imports
	 */
	private function addUseImport( array &$imports, string $class ) :void {
		$class = \trim( $class );
		if ( $class === '' ) {
			return;
		}

		if ( \preg_match( '#^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$#i', $class, $match ) === 1 ) {
			$class = \trim( $match[ 1 ], " \t\n\r\0\x0B\\" );
			$alias = $match[ 2 ];
		}
		else {
			$class = \trim( $class, " \t\n\r\0\x0B\\" );
			$parts = \explode( '\\', $class );
			$alias = (string)\end( $parts );
		}

		$imports[ $alias ] = $class;
	}

	/**
	 * @param array<string,string> $imports
	 */
	private function resolveClassExpression( string $classExpression, string $namespace, array $imports ) :string {
		if ( \strpos( $classExpression, '\\' ) === 0 ) {
			return \ltrim( $classExpression, '\\' );
		}

		$parts = \explode( '\\', $classExpression );
		$root = \array_shift( $parts );
		if ( \is_string( $root ) && isset( $imports[ $root ] ) ) {
			return $imports[ $root ].( empty( $parts ) ? '' : '\\'.\implode( '\\', $parts ) );
		}

		return \trim( $namespace.'\\'.$classExpression, " \t\n\r\0\x0B\\" );
	}

	private function classFilePathForPluginClass( string $class, string $pluginRoot ) :?string {
		if ( \strpos( $class, self::SHIELD_NAMESPACE ) !== 0 ) {
			return null;
		}

		return Path::join(
			$pluginRoot,
			'src',
			\str_replace( '\\', '/', \substr( $class, \strlen( self::SHIELD_NAMESPACE ) ) ).'.php'
		);
	}

	private function relativePluginPath( string $path, string $pluginRoot ) :string {
		return \str_replace( '\\', '/', Path::makeRelative( Path::normalize( $path ), Path::normalize( $pluginRoot ) ) );
	}

	/**
	 * @param list<string> $keys
	 * @return list<string>
	 */
	private function uniqueSorted( array $keys ) :array {
		$keys = \array_values( \array_unique( $keys ) );
		\sort( $keys );
		return $keys;
	}
}
