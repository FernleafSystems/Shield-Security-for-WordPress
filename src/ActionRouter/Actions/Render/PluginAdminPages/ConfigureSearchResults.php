<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Search\SearchResultsList;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

/**
 * Configure's search results, as the shared results list: one plain list, each result opening its goal in place.
 */
class ConfigureSearchResults extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminRequired;

	public const SLUG = 'render_configure_search_results';
	public const TEMPLATE = SearchResultsList::TEMPLATE;

	protected function getRenderData() :array {
		$results = ( new ConfigureSearchResultsBuilder() )->build( $this->fields()->string( 'search', '' ) );
		return SearchResultsList::renderData(
			[
				[
					'heading' => '',
					'results' => \array_map(
						fn( array $result ) :array => SearchResultsList::fromConfigureResult( $result, true ),
						$results
					),
				],
			],
			__( 'No Configure results matched this search.', 'wp-simple-firewall' )
		);
	}
}
