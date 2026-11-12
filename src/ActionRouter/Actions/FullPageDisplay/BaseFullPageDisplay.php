<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	ActionException,
	SecurityAdminRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\RenderActionTarget;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseFullPageDisplay extends BaseAction {

	use AuthNotRequired;

	protected function exec() {
		$router = self::con()->action_router;
		try {
			$this->setResponse(
				$router->action(
					Render::class,
					[
						'render_action_slug' => $this->action_data[ 'render_slug' ],
						'render_action_data' => $this->action_data[ 'render_data' ] ?? [],
					]
				)
			);
		}
		catch ( SecurityAdminRequiredException $e ) {
			// This action owns the page it outputs, so a refused page is shown as the Security Admin login.
			$this->response()
				 ->setPayload( [ 'render_output' => $router->render( PageSecurityAdminRestricted::class ) ] )
				 ->setPayloadSuccess( false );
		}
	}

	/**
	 * display page and die().
	 */
	protected function postExec() {
		$this->issueHeaders();
		$this->pushContent();
		$this->complete();
	}

	protected function pushContent() {
		$payload = $this->response()->payload();
		echo (string)( $payload[ 'render_output' ] ?? '' );
	}

	protected function isCacheDisabled() :bool {
		return true;
	}

	protected function issueHeaders() {
		\http_response_code( $this->getResponseCode() );
		nocache_headers();
		if ( $this->isCacheDisabled() ) {
			Services::WpGeneral()->turnOffCache();
		}
	}

	protected function complete() {
		die();
	}

	protected function getResponseCode() :int {
		return $this->isSuccess() ? $this->getSuccessCode() : $this->getFailureCode();
	}

	protected function getFailureCode() :int {
		return 403;
	}

	protected function getSuccessCode() :int {
		return 200;
	}

	protected function isSuccess() :bool {
		$payload = $this->response()->payload();
		return (bool)( $payload[ 'success' ] ?? false );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'render_slug'
		];
	}

	/**
	 * @throws ActionException
	 */
	protected function checkAvailableData() {
		parent::checkAvailableData();
		if ( \array_key_exists( 'render_slug', $this->action_data ) && !$this->isAllowedRenderSlug( (string)$this->action_data[ 'render_slug' ] ) ) {
			throw new ActionException( __( 'Invalid full page render target.', 'wp-simple-firewall' ) );
		}
	}

	protected function isAllowedRenderSlug( string $renderSlugOrClass ) :bool {
		$renderAction = RenderActionTarget::resolve( $renderSlugOrClass );
		return !empty( $renderAction ) && \in_array( $renderAction::SLUG, static::allowedRenderSlugs(), true );
	}

	public static function allowedRenderSlugs() :array {
		return [];
	}
}
