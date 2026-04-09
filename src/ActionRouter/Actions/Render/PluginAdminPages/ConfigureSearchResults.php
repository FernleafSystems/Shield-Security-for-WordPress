<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class ConfigureSearchResults extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminRequired;

	public const SLUG = 'render_configure_search_results';
	public const TEMPLATE = '/wpadmin/components/configure/search_results.twig';

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
