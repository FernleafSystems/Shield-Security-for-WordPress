<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class EventSlugSearch {

	use PluginControllerConsumer;

	public function findMatchingSlugs( string $searchText ) :array {
		$matchingSlugs = [];

		$words = $this->tokenize( $searchText );
		if ( !empty( $words ) ) {

			$events = self::con()->comps->events;
			foreach ( \array_keys( $events->getEvents() ) as $slug ) {
				$strings = $events->getEventStrings( $slug );
				if ( !empty( $strings ) ) {

					$searchableTexts = [];
					if ( !empty( $strings[ 'name' ] ) ) {
						$searchableTexts[] = $strings[ 'name' ];
					}
					foreach ( ( $strings[ 'audit' ] ?? [] ) as $auditString ) {
						$searchableTexts[] = \preg_replace( '#\{\{[a-z_]+}}#i', '', $auditString );
					}

					foreach ( $words as $word ) {
						foreach ( $searchableTexts as $text ) {
							if ( \stripos( $text, $word ) !== false ) {
								$matchingSlugs[] = $slug;
								break 2;
							}
						}
					}
				}
			}
		}

		return $matchingSlugs;
	}

	public function tokenize( string $searchText ) :array {
		$words = [];
		$searchText = \trim( $searchText );
		if ( !empty( $searchText ) ) {
			$words = \preg_split( '#\s+#', $searchText );
			if ( !empty( $words ) ) {
				$words = \array_values( \array_filter(
					$words,
					fn( $word ) => \strlen( $word ) > 2 && !\in_array( \strtolower( $word ), $this->getStopWords(), true )
				) );
			}
		}
		return $words;
	}

	/**
	 * Duplicated from (protected) \WP_Query::get_search_stopwords()
	 */
	private function getStopWords() :array {
		return (array)apply_filters(
			'wp_search_stopwords',
			\array_map(
				fn( $word ) => \strtolower( \trim( (string)$word ) ),
				\explode( ',', _x(
					'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www',
					'Comma-separated list of search stopwords in your language'
				) )
			)
		);
	}
}
