<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Legacy;

/**
 * @deprecated 19.2
 */
class GaspJs extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;

	public const SLUG = 'legacy_gasp_js';
	public const TEMPLATE = '/snippets/anti_bot/gasp_js.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'loading' => __( 'Loading', 'wp-simple-firewall' )
			],
		];
	}
}