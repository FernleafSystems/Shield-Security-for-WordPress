<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\{
	ActionsMap,
	AjaxRenderTargetPolicy,
	RenderActionTarget
};

class AjaxRender extends BaseAction {

	use AnyUserAuthRequired;
	use SecurityAdminNotRequired;

	public const SLUG = 'ajax_render';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		// The page names its render target by slug, like any other request names its action.
		$renderAction = RenderActionTarget::require(
			ActionsMap::ActionFromRequest( $this->fields()->string( 'render_slug' ) )
		);
		if ( !( new AjaxRenderTargetPolicy() )->isAllowed( $renderAction ) ) {
			throw new ActionException( __( 'Render target is not allowed.', 'wp-simple-firewall' ) );
		}

		$this->setResponse( self::con()->action_router->action( $renderAction, $this->renderData() ) );
	}

	/**
	 * The data the render is given: this request's data, without the field that named the render, and marked as
	 * coming from a request.
	 */
	protected function renderData() :array {
		$data = \array_diff_key( $this->action_data, [ 'render_slug' => true ] );
		$data[ BaseRender::DATA_FROM_REQUEST ] = true;
		return $data;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'render_slug'
		];
	}
}
