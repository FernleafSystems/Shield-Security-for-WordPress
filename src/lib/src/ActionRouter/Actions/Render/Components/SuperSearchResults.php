<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	PluginBadgeClose,
	Render\BaseRender,
	Traits};

class SuperSearchResults extends BaseRender {

	use Traits\SecurityAdminRequired;

	public const SLUG = 'render_super_search_results';
	public const TEMPLATE = '/wpadmin_pages/components/search/super_search_results.twig';

	protected function getRenderData() :array {
		$results = ( new SelectSearchData() )
			->setCon( $this->getCon() )
			->build( $this->action_data[ 'search' ] );
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