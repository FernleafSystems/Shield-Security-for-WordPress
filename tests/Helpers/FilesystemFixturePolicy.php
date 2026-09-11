<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

final class FilesystemFixturePolicy {

	public const REMEDIATION = 'Use TempDirLifecycleTrait with createTrackedTempDir(), createTrackedTempPath(), or createTrackedTempFile(), and cleanupTrackedTempDirs().';
	private const READ_ONLY_FOPEN_MODE = '__read_only__';
	private const SYSTEM_TEMP_CALL_PATTERN = '(?<![a-z0-9_>:\\\\])\\\\?sys_get_temp_dir\(\)';
	private const ENTROPY_CALL_PATTERN = '(?<![a-z0-9_>:\\\\])\\\\?(?:uniqid|random_bytes)\(';

	private const EXPRESSION_BOUNDARIES = [
		',', '=', '+=', '-=', '*=', '/=', '%=', '**=', '.=', '&=', '|=', '^=', '<<=', '>>=', '??=',
		'+', '-', '*', '/', '%', '**',
		'&&', '||', 'and', 'or', 'xor',
		'&', '|', '^', '<<', '>>', '??',
		'==', '===', '!=', '!==', '<>', '<', '>', '<=', '>=', '<=>', 'instanceof',
		'?', ':', '=>',
	];

	/**
	 * This test must exercise the lifecycle helper against the real system temp root.
	 * No other unit-test file is exempt from the fixture policy.
	 */
	private const EXEMPT_FILE = 'tests/Unit/Helpers/TempDirLifecycleTraitTest.php';

	/**
	 * @return array<int,array{file:string,line:int,message:string,remediation:string}>
	 */
	public function scanDirectory( string $directory ) :array {
		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && \strtolower( $file->getExtension() ) === 'php' ) {
				$files[] = $file->getPathname();
			}
		}
		\sort( $files );

		$violations = [];
		foreach ( $files as $file ) {
			$violations = \array_merge( $violations, $this->scanFile( $file ) );
		}
		return $violations;
	}

	/**
	 * @return array<int,array{file:string,line:int,message:string,remediation:string}>
	 */
	public function scanFile( string $file ) :array {
		$source = @\file_get_contents( $file );
		if ( !\is_string( $source ) ) {
			throw new \RuntimeException( 'Unable to read unit-test fixture source: '.$file );
		}
		return $this->scanSource( $source, $file );
	}

	/**
	 * @return array<int,array{file:string,line:int,message:string,remediation:string}>
	 */
	public function scanSource( string $source, string $file = 'synthetic.php' ) :array {
		if ( $this->isExemptFile( $file ) ) {
			return [];
		}

		$violations = [];
		foreach ( $this->statements( \token_get_all( $source ) ) as $statement ) {
			$tokens = $statement[ 'tokens' ];
			$code = $this->normaliseStatement( $tokens );
			$firstArgumentWriter = '/(?<![a-z0-9_>:\\\\])\\\\?(?:mkdir|touch|file_put_contents)\([^,;{}]*'
				.self::SYSTEM_TEMP_CALL_PATTERN.'/i';
			$destinationWriter = '/(?<![a-z0-9_>:\\\\])\\\\?(?:copy|rename)\([^,;{}]*,[^;{}]*'
				.self::SYSTEM_TEMP_CALL_PATTERN.'/i';
			$fopenWriter = '/(?<![a-z0-9_>:\\\\])\\\\?fopen\([^,;{}]*'.self::SYSTEM_TEMP_CALL_PATTERN
				.'[^,;{}]*,(?!\''.self::READ_ONLY_FOPEN_MODE.'\')/i';
			$message = null;
			if ( \preg_match( '/(?<![a-z0-9_>:\\\\])\\\\?tempnam\('.self::SYSTEM_TEMP_CALL_PATTERN.',/i', $code ) === 1 ) {
				$message = 'Direct tempnam(sys_get_temp_dir(), ...) fixture creation is prohibited.';
			}
			elseif ( $this->containsDirectEntropyCombination( $tokens ) ) {
				$message = 'System-temp paths combined with ad-hoc entropy bypass tracked fixture lifecycle.';
			}
			elseif ( \preg_match( $firstArgumentWriter, $code ) === 1
				 || \preg_match( $destinationWriter, $code ) === 1
				 || \preg_match( $fopenWriter, $code ) === 1 ) {
				$message = 'Ad-hoc filesystem fixture creation beneath the system temp root is prohibited.';
			}

			if ( $message !== null ) {
				$violations[] = [
					'file'        => $file,
					'line'        => $statement[ 'line' ],
					'message'     => $message,
					'remediation' => self::REMEDIATION,
				];
			}
		}
		return $violations;
	}

	private function isExemptFile( string $file ) :bool {
		$normalised = \str_replace( '\\', '/', $file );
		return \substr( $normalised, -\strlen( self::EXEMPT_FILE ) ) === self::EXEMPT_FILE;
	}

	/**
	 * @return array<int,array{tokens:array,line:int}>
	 */
	private function statements( array $tokens ) :array {
		$statements = [];
		$current = [];
		$line = null;
		foreach ( $tokens as $token ) {
			$current[] = $token;
			if ( \is_array( $token )
				 && !\in_array( $token[ 0 ], [ T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true )
				 && $line === null ) {
				$line = $token[ 2 ];
			}
			if ( $token === ';' || $token === '{' || $token === '}' ) {
				$statements[] = [ 'tokens' => $current, 'line' => $line ?? 1 ];
				$current = [];
				$line = null;
			}
		}
		if ( $current !== [] ) {
			$statements[] = [ 'tokens' => $current, 'line' => $line ?? 1 ];
		}
		return $statements;
	}

	private function normaliseStatement( array $tokens ) :string {
		$code = '';
		foreach ( $tokens as $token ) {
			if ( !\is_array( $token ) ) {
				$code .= $token;
				continue;
			}
			if ( \in_array( $token[ 0 ], [ T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_OPEN_TAG ], true ) ) {
				continue;
			}
			if ( $token[ 0 ] === T_CONSTANT_ENCAPSED_STRING ) {
				$code .= \preg_match( '/^([\'\"])r[bt]?\\1$/i', $token[ 1 ] ) === 1
					? "'".self::READ_ONLY_FOPEN_MODE."'"
					: "''";
			}
			else {
				$code .= $token[ 0 ] === T_ENCAPSED_AND_WHITESPACE ? "''" : $token[ 1 ];
			}
		}
		return $code;
	}

	private function containsDirectEntropyCombination( array $tokens ) :bool {
		foreach ( $this->topLevelExpressionSegments( $tokens ) as $segment ) {
			if ( $segment[ 'tokens' ] === [] ) {
				continue;
			}
			$code = $this->normaliseStatement( $segment[ 'tokens' ] );
			if ( $segment[ 'concatenated' ]
				 && $this->containsSystemTempCall( $code )
				 && $this->containsEntropyCall( $code ) ) {
				return true;
			}

			foreach ( $this->nestedBodies( $segment[ 'tokens' ] ) as $body ) {
				$bodyCode = $this->normaliseStatement( $body[ 'tokens' ] );
				if ( $body[ 'path_join' ]
					 && $this->containsSystemTempCall( $bodyCode )
					 && $this->containsEntropyCall( $bodyCode ) ) {
					return true;
				}
				if ( $this->containsDirectEntropyCombination( $body[ 'tokens' ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private function containsSystemTempCall( string $code ) :bool {
		return \preg_match( '/'.self::SYSTEM_TEMP_CALL_PATTERN.'/i', $code ) === 1;
	}

	private function containsEntropyCall( string $code ) :bool {
		return \preg_match( '/'.self::ENTROPY_CALL_PATTERN.'/i', $code ) === 1;
	}

	/**
	 * @return array<int,array{tokens:array,concatenated:bool}>
	 */
	private function topLevelExpressionSegments( array $tokens ) :array {
		$segments = [];
		$current = [];
		$concatenated = false;
		$depth = 0;
		foreach ( $tokens as $token ) {
			$text = $this->tokenText( $token );
			if ( \in_array( $text, [ '(', '[', '{' ], true ) ) {
				++$depth;
			}
			elseif ( \in_array( $text, [ ')', ']', '}' ], true ) ) {
				--$depth;
			}
			if ( $depth === 0 && \in_array( \strtolower( $text ), self::EXPRESSION_BOUNDARIES, true ) ) {
				$segments[] = [ 'tokens' => $current, 'concatenated' => $concatenated ];
				$current = [];
				$concatenated = false;
			}
			else {
				$concatenated = $concatenated || ( $depth === 0 && $text === '.' );
				$current[] = $token;
			}
		}
		$segments[] = [ 'tokens' => $current, 'concatenated' => $concatenated ];
		return $segments;
	}

	/**
	 * @return array<int,array{tokens:array,path_join:bool}>
	 */
	private function nestedBodies( array $tokens ) :array {
		$bodies = [];
		$count = \count( $tokens );
		for ( $index = 0; $index < $count; ++$index ) {
			$open = $this->tokenText( $tokens[ $index ] );
			if ( !\in_array( $open, [ '(', '[' ], true ) ) {
				continue;
			}
			$close = $open === '(' ? ')' : ']';
			$body = [];
			$depth = 1;
			$closeIndex = null;
			for ( $cursor = $index + 1; $cursor < $count; ++$cursor ) {
				$text = $this->tokenText( $tokens[ $cursor ] );
				if ( $text === $open ) {
					++$depth;
				}
				elseif ( $text === $close && --$depth === 0 ) {
					$closeIndex = $cursor;
					break;
				}
				$body[] = $tokens[ $cursor ];
			}
			if ( $closeIndex === null ) {
				continue;
			}
			$bodies[] = [
				'tokens' => $body,
				'path_join' => $open === '(' && $this->isPathJoinCall( $tokens, $index ),
			];
			$index = $closeIndex;
		}
		return $bodies;
	}

	private function isPathJoinCall( array $tokens, int $openIndex ) :bool {
		$method = $this->previousSignificantToken( $tokens, $openIndex - 1 );
		$separator = $method === null ? null : $this->previousSignificantToken( $tokens, $method - 1 );
		$class = $separator === null ? null : $this->previousSignificantToken( $tokens, $separator - 1 );
		return $method !== null && \strtolower( $this->tokenText( $tokens[ $method ] ) ) === 'join'
			   && $separator !== null && $this->tokenText( $tokens[ $separator ] ) === '::'
			   && $class !== null && \strtolower( $this->tokenText( $tokens[ $class ] ) ) === 'path';
	}

	private function previousSignificantToken( array $tokens, int $index ) :?int {
		for ( ; $index >= 0; --$index ) {
			$token = $tokens[ $index ];
			if ( !\is_array( $token )
				 || !\in_array( $token[ 0 ], [ T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
				return $index;
			}
		}
		return null;
	}

	private function tokenText( $token ) :string {
		return \is_array( $token ) ? $token[ 1 ] : $token;
	}
}
