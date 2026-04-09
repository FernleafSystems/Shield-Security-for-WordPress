<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

class SearchTextTokenBuilder {

	/**
	 * @param string[] $texts
	 */
	public function build( array $texts ) :string {
		$allWords = \array_filter( \array_map(
			static fn( string $word ) :string => \trim( \strtolower( $word ) ),
			\explode( ' ', \preg_replace(
				'#[():-]+#',
				' ',
				\strip_tags( \implode( ' ', $texts ) )
			) ?? '' )
		) );

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
}
