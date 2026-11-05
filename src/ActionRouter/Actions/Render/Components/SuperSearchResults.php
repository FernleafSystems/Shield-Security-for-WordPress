<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureSearchResultsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;

/**
 * The search dialog lists results the way Configure's search does: settings found by Configure's own search,
 * showing where each lives and its current value, then tools, IP addresses, integrations and links.
 */
class SuperSearchResults extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminRequired;

	public const SLUG = 'render_super_search_results';
	public const TEMPLATE = '/wpadmin/components/search/super_search_results.twig';

	private const MAX_SETTINGS = 10;

	protected function getRenderData() :array {
		$search = (string)( $this->action_data[ 'search' ] ?? '' );

		$sections = [];
		$settings = \array_slice( ( new ConfigureSearchResultsBuilder() )->build( $search ), 0, self::MAX_SETTINGS );
		if ( !empty( $settings ) ) {
			$sections[] = [
				'heading' => __( 'Settings', 'wp-simple-firewall' ),
				'results' => \array_map( fn( array $result ) :array => [
					'icon_class' => $result[ 'icon_class' ],
					'label'      => $result[ 'label' ],
					'type'       => $result[ 'type' ] === 'zone' ? __( 'Zone', 'wp-simple-firewall' ) : __( 'Option', 'wp-simple-firewall' ),
					'type_key'   => $result[ 'type' ],
					'path'       => $result[ 'path' ],
					'summary'    => $result[ 'summary' ],
					'value'      => $result[ 'value' ],
					'href'       => $result[ 'href' ],
					'target'     => '',
					'classes'    => [],
					'data'       => [],
				], $settings ),
			];
		}

		foreach ( ( new SelectSearchData() )->build( $search, false ) as $group ) {
			$sections[] = [
				'heading' => $group[ 'text' ],
				'results' => \array_map( fn( array $child ) :array => [
					'icon_class' => $child[ 'icon' ] ?? '',
					'label'      => $child[ 'text' ],
					'type'       => '',
					'type_key'   => '',
					'path'       => '',
					'summary'    => '',
					'value'      => '',
					'href'       => $child[ 'link' ][ 'href' ],
					'target'     => (string)( $child[ 'link' ][ 'target' ] ?: '' ),
					'classes'    => $child[ 'link' ][ 'classes' ],
					'data'       => $child[ 'link' ][ 'data' ],
				], $group[ 'children' ] ),
			];
		}

		$count = \array_sum( \array_map( fn( array $section ) :int => \count( $section[ 'results' ] ), $sections ) );
		return [
			'flags'   => [
				'has_results' => $count > 0,
			],
			'strings' => [
				'no_results'  => __( 'Sorry, there were no results for this search.', 'wp-simple-firewall' ),
				'best_label'  => __( 'Best match', 'wp-simple-firewall' ),
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
