<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;

abstract class OffCanvasBase extends BaseRender {

	const TEMPLATE = '/components/html/offcanvas_content.twig';

	protected function getRenderData() :array {
		return [
			'content' => [
				'canvas_title' => $this->buildCanvasTitle(),
				'canvas_body'  => $this->buildCanvasBody(),
			]
		];
	}

	protected function buildCanvasTitle() :string {
		return 'No canvas title';
	}

	protected function buildCanvasBody() :string {
		return 'No canvas body';
	}
}