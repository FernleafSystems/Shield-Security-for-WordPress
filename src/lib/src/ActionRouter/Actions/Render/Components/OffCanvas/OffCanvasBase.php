<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;

abstract class OffCanvasBase extends BaseRender {

	public const TEMPLATE = '/components/html/offcanvas_content.twig';

	protected function getRenderData() :array {
		return [
			'content' => [
				'canvas_title' => $this->buildCanvasTitle(),
				'canvas_body'  => $this->buildCanvasBody(),
			]
		];
	}

	protected function buildCanvasTitle() :string {
		return '';
	}

	protected function buildCanvasBody() :string {
		return 'No canvas body';
	}
}