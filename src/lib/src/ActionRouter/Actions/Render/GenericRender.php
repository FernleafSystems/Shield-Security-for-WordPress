<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

/**
 * Shouldn't really be used going forward, but provided as a means of transitioning legacy rendering to render actions
 * @deprecated 18.5.10
 */
class GenericRender extends BaseRender {

	public const SLUG = 'generic_render';

	protected function getRenderData() :array {
		die();
		return $this->action_data[ 'render_action_data' ] ?? [];
	}
}