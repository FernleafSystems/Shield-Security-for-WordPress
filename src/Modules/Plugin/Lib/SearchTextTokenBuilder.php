<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

class SearchTextTokenBuilder {

	/**
	 * @param string[] $texts
	 */
	public function build( array $texts ) :string {
		$allWords = $this->extractNormalizedTokens( \strip_tags( \implode( ' ', $texts ) ) );

		return \implode(
			' ',
			\array_unique( \array_filter(
				\array_merge(
					$allWords,
					\array_map(
						static function ( string $word ) :?string {
							return \preg_match( '#s$#i', $word ) ? null : $word.'s';
						},
						$allWords
					),
					\array_map(
						static function ( string $word ) :?string {
							$trimmed = \rtrim( $word, 's' );
							return $trimmed === $word ? null : $trimmed;
						},
						$allWords
					)
				),
				static fn( ?string $word ) :bool => !empty( $word ) && \strlen( $word ) > 2
			) )
		);
	}

	/**
	 * @return list<string>
	 */
	public function extractTerms( string $search ) :array {
		return $this->extractNormalizedTokens( $search );
	}

	/**
	 * @param list<string> $needles
	 */
	public function countMatches( string $haystack, array $needles ) :int {
		return \count( \array_intersect(
			$needles,
			\array_map( '\trim', \explode( ' ', \strtolower( $haystack ) ) )
		) );
	}

	/**
	 * @return list<string>
	 */
	private function extractNormalizedTokens( string $text ) :array {
		$text = \strtolower( \trim( $text ) );
		if ( $text === '' ) {
			return [];
		}

		$compoundWords = [];
		if ( \preg_match_all( '#[a-z0-9]+(?:-[a-z0-9]+)+#i', $text, $matches ) > 0 ) {
			$compoundWords = \array_map(
				static fn( string $word ) :string => \str_replace( '-', '', $word ),
				$matches[ 0 ]
			);
		}

		$splitWords = \preg_split(
			'#\s+#',
			\preg_replace(
				'#[():-]+#',
				' ',
				$text
			) ?? '',
			-1,
			PREG_SPLIT_NO_EMPTY
		);

		return \array_values( \array_unique( \array_filter(
			\array_merge( \is_array( $splitWords ) ? $splitWords : [], $compoundWords ),
			static fn( string $word ) :bool => \strlen( $word ) > 2
		) ) );
	}
}
