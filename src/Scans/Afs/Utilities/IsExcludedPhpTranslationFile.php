<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Services\Services;

class IsExcludedPhpTranslationFile {

	public function check( string $path ) :bool {
		$isExcluded = false;

		$normal = wp_normalize_path( $path );
		if ( $this->isWithinWordpressLangDir( $normal )
			 && $this->isLanguagePhpTranslationFilename( \basename( $normal ) )
		) {
			$content = (string)Services::WpFs()->getFileContent( $normal );
			$isExcluded = !empty( $content ) && $this->isSafeTranslationPhpPayload( $content );
		}

		return $isExcluded;
	}

	protected function isSafeTranslationPhpPayload( string $content ) :bool {
		return $this->hasExpectedTokensOnly( $content ) && $this->hasExpectedTranslationStructure( $content );
	}

	protected function hasExpectedTranslationStructure( string $content ) :bool {
		$tokens = $this->tokenizeContent( $content );
		if ( empty( $tokens ) ) {
			return false;
		}

		$significant = \array_values( \array_filter(
			$tokens,
			function ( $token ) {
				return !\is_array( $token ) || !\in_array( $token[ 0 ], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true );
			}
		) );

		$index = 0;
		$hasMessages = false;

		if ( !$this->matchTokenType( $significant, $index, T_OPEN_TAG ) ) {
			return false;
		}
		$index++;

		if ( !$this->matchTokenType( $significant, $index, T_RETURN ) ) {
			return false;
		}
		$index++;

		if ( !$this->parseArrayValue( $significant, $index, true, $hasMessages ) ) {
			return false;
		}

		if ( !$this->matchLiteralToken( $significant, $index, ';' ) ) {
			return false;
		}
		$index++;

		if ( $this->matchTokenType( $significant, $index, T_CLOSE_TAG ) ) {
			$index++;
		}

		return $hasMessages && $index === \count( $significant );
	}

	/**
	 * WordPress' canonical language location is WP_LANG_DIR.
	 * We also include common standard roots for compatibility.
	 */
	protected function isWithinWordpressLangDir( string $path ) :bool {
		$pathDir = trailingslashit( \dirname( $path ) );
		foreach ( $this->possibleLanguageRoots() as $langDir ) {
			if ( \str_starts_with( $pathDir, $langDir ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match WordPress core/plugin/theme language pack naming conventions for PHP translation files.
	 */
	protected function isLanguagePhpTranslationFilename( string $fileName ) :bool {
		return \preg_match(
				   '#^(?:(?:admin|admin-network|continents-cities|ms)-)?[a-z]{2,3}(?:_[A-Z]{2})?(?:_(?:in)?formal)?\.l10n\.php$#i',
				   $fileName
			   ) > 0
			   ||
			   \preg_match( '#^[a-z0-9._-]+-[a-z]{2,3}(?:_[A-Z]{2})?(?:_(?:in)?formal)?\.l10n\.php$#i', $fileName ) > 0;
	}

	protected function hasExpectedTokensOnly( string $content ) :bool {
		$tokens = $this->tokenizeContent( $content );
		if ( empty( $tokens ) ) {
			return false;
		}

		foreach ( $tokens as $token ) {
			if ( \is_array( $token ) ) {
				if ( !$this->isAllowedNamedToken( $token[ 0 ], (string)$token[ 1 ] ) ) {
					return false;
				}
			}
			elseif ( !$this->isAllowedLiteralToken( $token ) ) {
				return false;
			}
		}

		return true;
	}

	protected function parseArrayValue( array $tokens, int &$index, bool $trackMessages, bool &$hasMessages ) :bool {
		if ( $this->matchLiteralToken( $tokens, $index, '[' ) ) {
			$close = ']';
			$index++;
		}
		elseif ( $this->matchTokenType( $tokens, $index, T_ARRAY ) ) {
			$close = ')';
			$index++;
			if ( !$this->matchLiteralToken( $tokens, $index, '(' ) ) {
				return false;
			}
			$index++;
		}
		else {
			return false;
		}

		if ( $this->matchLiteralToken( $tokens, $index, $close ) ) {
			$index++;
			return true;
		}

		while ( true ) {
			$keyOrValue = $this->parseValue( $tokens, $index );
			if ( $keyOrValue === null ) {
				return false;
			}

			$value = $keyOrValue;
			if ( $this->matchTokenType( $tokens, $index, T_DOUBLE_ARROW ) ) {
				$index++;

				if ( $keyOrValue[ 'kind' ] !== 'scalar' ) {
					return false;
				}

				$value = $this->parseValue( $tokens, $index );
				if ( $value === null ) {
					return false;
				}

				if ( $trackMessages
					 && $keyOrValue[ 'type' ] === 'string'
					 && $keyOrValue[ 'value' ] === 'messages'
					 && $value[ 'kind' ] === 'array'
				) {
					$hasMessages = true;
				}
			}

			if ( $this->matchLiteralToken( $tokens, $index, ',' ) ) {
				$index++;
				if ( $this->matchLiteralToken( $tokens, $index, $close ) ) {
					$index++;
					return true;
				}
				continue;
			}

			if ( $this->matchLiteralToken( $tokens, $index, $close ) ) {
				$index++;
				return true;
			}

			return false;
		}
	}

	/**
	 * @return array{kind:string,type:string,value:mixed}|null
	 */
	protected function parseValue( array $tokens, int &$index ) :?array {
		$token = $tokens[ $index ] ?? null;
		if ( $token === null ) {
			return null;
		}

		if ( $this->matchLiteralToken( $tokens, $index, '[' ) || $this->matchTokenType( $tokens, $index, T_ARRAY ) ) {
			$hasMessages = false;
			if ( !$this->parseArrayValue( $tokens, $index, false, $hasMessages ) ) {
				return null;
			}
			return [
				'kind'  => 'array',
				'type'  => 'array',
				'value' => null,
			];
		}

		if ( \is_array( $token ) ) {
			if ( \in_array( $token[ 0 ], [ T_LNUMBER, T_DNUMBER ], true ) ) {
				$index++;
				return [
					'kind'  => 'scalar',
					'type'  => 'number',
					'value' => (string)$token[ 1 ],
				];
			}

			if ( $token[ 0 ] === T_CONSTANT_ENCAPSED_STRING ) {
				$decoded = $this->decodePhpString( (string)$token[ 1 ] );
				if ( $decoded === null ) {
					return null;
				}
				$index++;
				return [
					'kind'  => 'scalar',
					'type'  => 'string',
					'value' => $decoded,
				];
			}

			if ( $token[ 0 ] === T_STRING ) {
				$constant = \strtolower( (string)$token[ 1 ] );
				if ( \in_array( $constant, [ 'true', 'false', 'null' ], true ) ) {
					$index++;
					return [
						'kind'  => 'scalar',
						'type'  => $constant,
						'value' => $constant,
					];
				}
			}
		}

		return null;
	}

	protected function decodePhpString( string $literal ) :?string {
		$length = \strlen( $literal );
		if ( $length < 2 ) {
			return null;
		}

		$quote = $literal[ 0 ];
		if ( !\in_array( $quote, [ "'", '"' ], true ) || $literal[ $length - 1 ] !== $quote ) {
			return null;
		}

		$inner = \substr( $literal, 1, -1 );

		if ( $quote === "'" ) {
			return \str_replace(
				[ "\\\\", "\\'" ],
				[ "\\", "'" ],
				$inner
			);
		}

		return \stripcslashes( $inner );
	}

	protected function matchLiteralToken( array $tokens, int $index, string $literal ) :bool {
		return isset( $tokens[ $index ] ) && !\is_array( $tokens[ $index ] ) && $tokens[ $index ] === $literal;
	}

	protected function matchTokenType( array $tokens, int $index, int $type ) :bool {
		return isset( $tokens[ $index ] ) && \is_array( $tokens[ $index ] ) && $tokens[ $index ][ 0 ] === $type;
	}

	protected function tokenizeContent( string $content ) :?array {
		$normalized = $this->normalizeContent( $content );
		if ( $normalized === '' ) {
			return null;
		}

		try {
			return \token_get_all( $normalized, \defined( 'TOKEN_PARSE' ) ? TOKEN_PARSE : 0 );
		}
		catch ( \ParseError $e ) {
			return null;
		}
	}

	protected function normalizeContent( string $content ) :string {
		if ( \str_starts_with( $content, "\xEF\xBB\xBF" ) ) {
			$content = (string)\substr( $content, 3 );
		}
		return \ltrim( $content );
	}

	protected function isAllowedNamedToken( int $tokenType, string $tokenText ) :bool {
		if ( \in_array( $tokenType, [
			T_OPEN_TAG,
			T_CLOSE_TAG,
			T_RETURN,
			T_ARRAY,
			T_WHITESPACE,
			T_COMMENT,
			T_DOC_COMMENT,
			T_CONSTANT_ENCAPSED_STRING,
			T_LNUMBER,
			T_DNUMBER,
			T_DOUBLE_ARROW,
		], true ) ) {
			return true;
		}

		return $tokenType === T_STRING && \in_array( \strtolower( $tokenText ), [ 'true', 'false', 'null' ], true );
	}

	protected function isAllowedLiteralToken( string $token ) :bool {
		return \in_array( $token, [
			'[',
			']',
			'(',
			')',
			',',
			';',
		], true );
	}

	protected function possibleLanguageRoots() :array {
		$roots = [];

		if ( \defined( 'WP_LANG_DIR' ) ) {
			$roots[] = WP_LANG_DIR;
		}
		if ( \defined( 'WP_CONTENT_DIR' ) ) {
			$roots[] = path_join( WP_CONTENT_DIR, 'languages' );
		}
		if ( \defined( 'ABSPATH' ) && \is_string( ABSPATH ) ) {
			$roots[] = path_join( ABSPATH, 'wp-content/languages' );
			$roots[] = path_join( ABSPATH, 'wp-includes/languages' );
		}

		return \array_values( \array_filter( \array_unique( \array_map(
			fn( $dir ) => trailingslashit( wp_normalize_path( (string)$dir ) ),
			$roots
		) ) ) );
	}

}
