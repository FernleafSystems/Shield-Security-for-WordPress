<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

class PhpFileActivityClassifier {

	/**
	 * @phpstan-return value-of<PhpFileActivity::ALL>
	 */
	public function classify( string $path ) :string {
		if ( !\is_readable( $path ) ) {
			return PhpFileActivity::Unreadable;
		}

		$content = \file_get_contents( $path );
		if ( $content === false ) {
			return PhpFileActivity::Unreadable;
		}

		$content = $this->normalizeContent( $content );
		if ( $content === '' ) {
			return PhpFileActivity::Inert;
		}

		try {
			$tokens = \token_get_all( $content, \defined( 'TOKEN_PARSE' ) ? TOKEN_PARSE : 0 );
		}
		catch ( \ParseError $e ) {
			return PhpFileActivity::Invalid;
		}

		return $this->hasRuntimeTokens( $tokens ) ? PhpFileActivity::Executable : PhpFileActivity::Inert;
	}

	private function normalizeContent( string $content ) :string {
		if ( \str_starts_with( $content, "\xEF\xBB\xBF" ) ) {
			$content = (string)\substr( $content, 3 );
		}
		return \trim( $content );
	}

	private function hasRuntimeTokens( array $tokens ) :bool {
		$significant = [];
		foreach ( $tokens as $token ) {
			if ( $this->isInertToken( $token ) ) {
				continue;
			}
			$significant[] = $token;
		}

		if ( empty( $significant ) ) {
			return false;
		}

		$index = 0;
		while ( $this->consumeDeclareStatement( $significant, $index ) ) {
		}

		return $index < \count( $significant );
	}

	/**
	 * @param array{0:int,1:string}|string $token
	 */
	private function isInertToken( $token ) :bool {
		if ( !\is_array( $token ) ) {
			return \trim( $token ) === '';
		}

		if ( \in_array( $token[ 0 ], [
			T_OPEN_TAG,
			T_CLOSE_TAG,
			T_WHITESPACE,
			T_COMMENT,
			T_DOC_COMMENT,
		], true ) ) {
			return true;
		}

		return $token[ 0 ] === T_INLINE_HTML && \trim( $token[ 1 ] ) === '';
	}

	private function consumeDeclareStatement( array $tokens, int &$index ) :bool {
		if ( !$this->matchTokenType( $tokens, $index, T_DECLARE ) ) {
			return false;
		}

		$cursor = $index + 1;
		if ( !$this->matchLiteral( $tokens, $cursor, '(' ) ) {
			return false;
		}

		$depth = 0;
		while ( isset( $tokens[ $cursor ] ) ) {
			if ( $this->matchLiteral( $tokens, $cursor, '(' ) ) {
				$depth++;
			}
			elseif ( $this->matchLiteral( $tokens, $cursor, ')' ) ) {
				$depth--;
				if ( $depth === 0 ) {
					$cursor++;
					break;
				}
			}
			$cursor++;
		}

		if ( $depth !== 0 || !$this->matchLiteral( $tokens, $cursor, ';' ) ) {
			return false;
		}

		$index = $cursor + 1;
		return true;
	}

	private function matchTokenType( array $tokens, int $index, int $type ) :bool {
		return isset( $tokens[ $index ] ) && \is_array( $tokens[ $index ] ) && $tokens[ $index ][ 0 ] === $type;
	}

	private function matchLiteral( array $tokens, int $index, string $literal ) :bool {
		return isset( $tokens[ $index ] ) && !\is_array( $tokens[ $index ] ) && $tokens[ $index ] === $literal;
	}
}
