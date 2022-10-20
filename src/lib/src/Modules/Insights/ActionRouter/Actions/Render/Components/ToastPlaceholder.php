<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits;

class ToastPlaceholder extends BasePlugin {

	use Traits\AuthNotRequired;

	const SLUG = 'render_toast_placeholder';
	const TEMPLATE = '/snippets/toaster.twig';

	protected function getRenderData() :array {
		return [
			'strings'     => [
				'title' => $this->getCon()->getHumanName(),
			],
		];
	}
}