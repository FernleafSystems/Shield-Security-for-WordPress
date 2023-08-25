<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

/**
 * @deprecated 18.3.0
 */
class InfoKeyStats extends BaseBuilder {

	public const SLUG = 'report_info_keystats';
	public const TEMPLATE = '/components/reports/components/info_keystats.twig';

	protected function getRenderData() :array {
		return [
			'flags'   => [
				'render_required' => false,
			],
			'strings' => [
				'title' => __( 'Top Security Statistics', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'counts' => [],
			],
		];
	}
}