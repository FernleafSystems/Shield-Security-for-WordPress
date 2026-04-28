<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

abstract class OffCanvasBase extends BaseRender {

	public const TEMPLATE = '/components/html/offcanvas_content.twig';

	protected function getRenderData() :array {
		$title = \trim( $this->buildCanvasTitle() );
		if ( $title === '' ) {
			throw new ActionException( sprintf( 'Offcanvas render action %s must provide a canvas title.', static::class ) );
		}

		return [
			'content' => [
				'canvas_title' => $title,
				'canvas_body'  => $this->buildCanvasBody(),
			]
		];
	}

	abstract protected function buildCanvasTitle() :string;

	protected function buildCanvasBody() :string {
		return __( 'No canvas body', 'wp-simple-firewall' );
	}
}
