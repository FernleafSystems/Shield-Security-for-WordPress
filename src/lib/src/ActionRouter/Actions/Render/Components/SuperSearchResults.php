<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;

class SuperSearchResults extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminRequired;

	public const SLUG = 'render_super_search_results';
	public const TEMPLATE = '/wpadmin/components/search/super_search_results.twig';

	protected function getRenderData() :array {
		$results = ( new SelectSearchData() )->build( $this->action_data[ 'search' ] );
		return [
			'flags'   => [
				'has_results' => !empty( $results ),
			],
			'strings' => [
				'no_results' => __( 'Sorry, there were no results for this search.' ),
			],
			'vars'    => [
				'results' => $results,
			],
		];
	}
}