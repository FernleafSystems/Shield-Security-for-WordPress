<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;

/**
 * Shouldn't really be used going forward, but provided as a means of transitioning legacy rendering to render actions
 */
class GenericRender extends BaseRender {

	use AuthNotRequired;

	public const SLUG = 'generic_render';

	protected function getRenderData() :array {
		return $this->action_data[ 'render_action_data' ] ?? [];
	}
}