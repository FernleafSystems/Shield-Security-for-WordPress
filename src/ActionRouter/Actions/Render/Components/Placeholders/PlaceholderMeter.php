<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Placeholders;

class PlaceholderMeter extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_loading_placeholder_meter';
	public const TEMPLATE = '/components/html/loading_placeholders/placeholder_meter.twig';

	protected function getRenderData() :array {
		return [
			'vars' => [
				'meter_slug' => $this->action_data[ 'meter_slug' ],
			]
		];
	}
}