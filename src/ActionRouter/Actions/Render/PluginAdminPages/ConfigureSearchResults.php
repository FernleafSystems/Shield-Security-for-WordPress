<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

/**
 * @phpstan-import-type ConfigureSearchResult from ConfigureLandingRenderContracts
 */
class ConfigureSearchResults extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminRequired;

	public const SLUG = 'render_configure_search_results';
	public const TEMPLATE = '/wpadmin/components/configure/search_results.twig';

	/**
	 * @return array{
	 *   flags:array{has_results:bool},
	 *   strings:array{no_results:string,zone_label:string,option_label:string},
	 *   vars:array{results:list<ConfigureSearchResult>}
	 * }
	 */
	protected function getRenderData() :array {
		$results = ( new ConfigureSearchResultsBuilder() )->build( (string)( $this->action_data[ 'search' ] ?? '' ) );

		return [
			'flags'   => [
				'has_results' => !empty( $results ),
			],
			'strings' => [
				'no_results'   => __( 'No Configure results matched this search.', 'wp-simple-firewall' ),
				'zone_label'   => __( 'Zone', 'wp-simple-firewall' ),
				'option_label' => __( 'Option', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'results' => $results,
			],
		];
	}
}
