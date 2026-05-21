<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	ActionRoutingController,
	Actions
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ExternalActionTransportPolicy {

	use PluginControllerConsumer;

	public function isAllowed( string $actionSlug, array $transport, int $type ) :bool {
		$action = ActionsMap::ActionFromSlug( $actionSlug );
		if ( !empty( $action ) ) {
			$actionSlug = $action::SLUG;
		}

		switch ( $actionSlug ) {
			case Actions\Render::SLUG:
				return false;

			case Actions\AjaxRender::SLUG:
				return $type === ActionRoutingController::ACTION_AJAX;

			case Actions\FullPageDisplay\FullPageDisplayDynamic::SLUG:
				return $this->isAllowedMainwpDynamicIframeTransport( $transport, $type );

			case Actions\FullPageDisplay\DisplayBlockPage::SLUG:
				return $this->isAllowedPublicBlockPageTransport( $transport, $type );

			case Actions\FullPageDisplay\FullPageDisplayNonTerminating::SLUG:
				return false;

			case Actions\FullPageDisplay\DisplayReport::SLUG:
				return $type === ActionRoutingController::ACTION_SHIELD;

			case Actions\FullPageDisplay\DisplayReportAdmin::SLUG:
				return $type === ActionRoutingController::ACTION_SHIELD
					   && ( \is_admin() || \is_network_admin() );

			default:
				return !\is_a( $action, Actions\Render\BaseRender::class, true )
					   || $this->isAllowedDirectRenderTransport( $actionSlug, $type );
		}
	}

	private function isAllowedDirectRenderTransport( string $actionSlug, int $type ) :bool {
		return \in_array(
			$type,
			$this->directRenderExternalTransportAllowlist()[ $actionSlug ] ?? [],
			true
		);
	}

	/**
	 * @return array<string,list<int>>
	 */
	private function directRenderExternalTransportAllowlist() :array {
		return [];
	}

	private function isAllowedMainwpDynamicIframeTransport( array $transport, int $type ) :bool {
		if ( $type !== ActionRoutingController::ACTION_SHIELD ) {
			return false;
		}

		$renderAction = RenderActionTarget::resolve( (string)( $transport[ 'render_slug' ] ?? '' ) );
		if ( $renderAction !== Actions\Render\FullPage\MainWP\TabManageSitePage::class ) {
			return false;
		}

		$renderData = $transport[ 'render_data' ] ?? null;
		if ( !\is_array( $renderData ) || (int)( $renderData[ 'site_id' ] ?? 0 ) < 1 ) {
			return false;
		}

		$baseCapability = (string)( self::con()->cfg->properties[ 'base_permissions' ] ?? 'manage_options' );
		return \is_user_logged_in()
			   && \current_user_can( $baseCapability )
			   && ActionNonce::Verify(
				Actions\FullPageDisplay\FullPageDisplayDynamic::class,
				(string)( $transport[ ActionData::FIELD_NONCE ] ?? '' )
			   );
	}

	private function isAllowedPublicBlockPageTransport( array $transport, int $type ) :bool {
		if ( $type !== ActionRoutingController::ACTION_SHIELD || \array_key_exists( 'render_data', $transport ) ) {
			return false;
		}

		return \in_array(
			$this->renderTargetSlug( (string)( $transport[ 'render_slug' ] ?? '' ) ),
			Actions\FullPageDisplay\DisplayBlockPage::allowedRenderSlugs(),
			true
		);
	}

	private function renderTargetSlug( string $renderSlugOrClass ) :string {
		$renderAction = RenderActionTarget::resolve( $renderSlugOrClass );
		return empty( $renderAction ) ? '' : $renderAction::SLUG;
	}
}
