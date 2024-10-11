<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;

class ToastPlaceholder extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use AuthNotRequired;

	public const SLUG = 'render_toast_placeholder';
	public const TEMPLATE = '/snippets/toaster.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'title' => self::con()->labels->Name,
			],
		];
	}
}