<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Legacy;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits;

class GaspJs extends BaseRender {

	use Traits\AuthNotRequired;

	const SLUG = 'legacy_gasp_js';
	const TEMPLATE = '/snippets/anti_bot/gasp_js.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'loading' => __( 'Loading', 'wp-simple-firewall' )
			],
		];
	}
}