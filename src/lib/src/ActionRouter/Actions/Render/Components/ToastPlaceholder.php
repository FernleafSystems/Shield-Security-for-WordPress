<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

class ToastPlaceholder extends BasePlugin {

	use Traits\AuthNotRequired;

	public const SLUG = 'render_toast_placeholder';
	public const TEMPLATE = '/snippets/toaster.twig';

	protected function getRenderData() :array {
		return [
			'strings'     => [
				'title' => $this->getCon()->getHumanName(),
			],
		];
	}
}