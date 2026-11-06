<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Search\SearchResultsList;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureSearchResultsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;

/**
 * The search dialog's results, in the shared results list: settings found by Configure's own search, then tools,
 * IP addresses, integrations and links, each kind under its heading.
 */
class SuperSearchResults extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminRequired;

	public const SLUG = 'render_super_search_results';
	public const TEMPLATE = SearchResultsList::TEMPLATE;

	private const MAX_SETTINGS = 10;

	protected function getRenderData() :array {
		$search = (string)( $this->action_data[ 'search' ] ?? '' );

		$sections = [
			[
				'heading' => __( 'Settings', 'wp-simple-firewall' ),
				'results' => \array_map(
					fn( array $result ) :array => SearchResultsList::fromConfigureResult( $result, false ),
					\array_slice( ( new ConfigureSearchResultsBuilder() )->build( $search ), 0, self::MAX_SETTINGS )
				),
			],
		];

		foreach ( ( new SelectSearchData() )->build( $search, false ) as $group ) {
			$sections[] = [
				'heading' => $group[ 'text' ],
				'results' => \array_map( fn( array $child ) :array => [
					'label'       => $child[ 'text' ],
					'type_label'  => '',
					'path'        => '',
					'summary'     => '',
					'value'       => '',
					'href'        => $child[ 'link' ][ 'href' ],
					'is_external' => $child[ 'link' ][ 'target' ] === '_blank',
					'classes'     => $child[ 'link' ][ 'classes' ],
					'attributes'  => \array_combine(
						\array_map( fn( string $key ) :string => 'data-'.$key, \array_keys( $child[ 'link' ][ 'data' ] ) ),
						\array_map( 'strval', \array_values( $child[ 'link' ][ 'data' ] ) )
					),
				], $group[ 'children' ] ),
			];
		}

		return SearchResultsList::renderData(
			$sections,
			__( 'Sorry, there were no results for this search.', 'wp-simple-firewall' )
		);
	}
}
