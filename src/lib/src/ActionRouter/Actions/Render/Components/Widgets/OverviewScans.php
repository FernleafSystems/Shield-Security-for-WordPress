<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

class OverviewScans extends OverviewBase {

	public const TEMPLATE = '/wpadmin/components/widget/overview_scans.twig';

	protected function getRenderData() :array {
		return [
			'flags'   => [
				'has_results' => $this->action_data[ 'count' ] > 0,
			],
			'strings' => [
			],
			'vars'    => [
				'count' => $this->action_data[ 'count' ],
			],
		];
	}
}