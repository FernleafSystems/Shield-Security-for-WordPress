<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Search;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureLandingRenderContracts;

/**
 * One results list for every search: Configure's own, and the search dialog's. Both render
 * search/results_list.twig with what this builds, so a result reads the same wherever it's found.
 *
 * @phpstan-import-type ConfigureSearchResult from ConfigureLandingRenderContracts
 * @phpstan-type SearchListResult array{
 *   label:string,
 *   type_label:string,
 *   path:string,
 *   summary:string,
 *   value:string,
 *   href:string,
 *   is_external:bool,
 *   classes:list<string>,
 *   attributes:array<string,string>
 * }
 * @phpstan-type SearchListSection array{heading:string,results:list<SearchListResult>}
 */
class SearchResultsList {

	public const TEMPLATE = '/wpadmin/components/search/results_list.twig';

	/**
	 * A setting or goal found by Configure's search. Within Configure, a result opens its goal in place,
	 * so it carries what the page needs for that; elsewhere its link opens Configure on it.
	 * @param ConfigureSearchResult $result
	 * @return SearchListResult
	 */
	public static function fromConfigureResult( array $result, bool $opensInPlace ) :array {
		return [
			'label'       => $result[ 'label' ],
			// Settings are the norm, so only goals are labelled.
			'type_label'  => $result[ 'type' ] === 'zone' ? __( 'Goal', 'wp-simple-firewall' ) : '',
			'path'        => $result[ 'path' ],
			'summary'     => $result[ 'summary' ],
			'value'       => $result[ 'value' ],
			'href'        => $result[ 'href' ],
			'is_external' => false,
			'classes'     => [],
			'attributes'  => $opensInPlace
				? [
					'data-configure-search-result' => '1',
					'data-drill-zone-selection'    => $result[ 'selection_json' ],
					'data-configure-focus-request' => $result[ 'focus_request_json' ],
				]
				: [],
		];
	}

	/**
	 * @param list<SearchListSection> $sections
	 * @return array{flags:array{has_results:bool},strings:array<string,string>,vars:array{sections:list<SearchListSection>}}
	 */
	public static function renderData( array $sections, string $noResults ) :array {
		$sections = \array_values( \array_filter( $sections, fn( array $section ) :bool => !empty( $section[ 'results' ] ) ) );
		$count = \array_sum( \array_map( fn( array $section ) :int => \count( $section[ 'results' ] ), $sections ) );
		return [
			'flags'   => [
				'has_results' => $count > 0,
			],
			'strings' => [
				'no_results'  => $noResults,
				'best_label'  => __( 'Best match', 'wp-simple-firewall' ),
				'more_label'  => __( 'Other matches', 'wp-simple-firewall' ),
				/* translators: %s: number of search results */
				'count_label' => \sprintf( _n( '%s result', '%s results', $count, 'wp-simple-firewall' ), $count ),
				'move_label'  => __( 'move', 'wp-simple-firewall' ),
				'open_label'  => __( 'open', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'sections' => $sections,
			],
		];
	}
}
