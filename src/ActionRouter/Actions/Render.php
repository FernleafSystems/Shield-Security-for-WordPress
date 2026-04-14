<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\RenderActionTarget;
use FernleafSystems\Wordpress\Services\Services;

/**
 * This is the entry point for ALL rendering. This is the action that should be called, which will then delegate
 * the rendering further onward. This will allow us to customize the rendering data and environment for all
 * renders before they're ever processed.
 */
class Render extends BaseAction {

	use Traits\AuthNotRequired;
	use Traits\ByPassIpBlock;
	use Traits\NonceVerifyNotRequired;

	public const SLUG = 'render';

	protected function exec() {
		$renderAction = RenderActionTarget::require( (string)$this->action_data[ 'render_action_slug' ] );
		$this->setResponse(
			self::con()->action_router->action(
				$renderAction,
				\array_filter( $this->action_data[ 'render_action_data' ] ?? [], fn( $item ) => !\is_null( $item ) )
			)
		);
	}

	protected function getRequiredDataKeys() :array {
		return [
			'render_action_slug',
			'render_action_data'
		];
	}
}
