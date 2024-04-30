<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ActionRoutingController {

	use ExecOnce;
	use PluginControllerConsumer;

	public const ACTION_AJAX = 1;
	public const ACTION_SHIELD = 2;
	public const ACTION_REST = 3;

	protected function run() {
		( new CaptureRedirects() )->run();
		( new CapturePluginAction() )->execute();
		( new CaptureAjaxAction() )->execute();
		( new CaptureRestApiAction() )->execute();
	}

	/**
	 * @throws Exceptions\ActionDoesNotExistException
	 * @throws Exceptions\ActionException
	 * @throws Exceptions\ActionTypeDoesNotExistException
	 * @throws Exceptions\SecurityAdminRequiredException
	 * @throws Exceptions\InvalidActionNonceException
	 */
	public function action( string $classOrSlug, array $data = [], int $type = self::ACTION_SHIELD ) :ActionResponse {

		switch ( $type ) {
			case self::ACTION_AJAX:
				$adapter = new ResponseAdapter\AjaxResponseAdapter();
				break;
			case self::ACTION_SHIELD:
				$adapter = new ResponseAdapter\ShieldActionResponseAdapter();
				break;
			case self::ACTION_REST:
				$adapter = new ResponseAdapter\RestApiActionResponseAdapter();
				break;
			default:
				throw new Exceptions\ActionTypeDoesNotExistException( $type );
		}

		try {
			$response = ( new ActionProcessor() )->processAction( $classOrSlug, $data );
		}
		catch ( Exceptions\SecurityAdminRequiredException $sare ) {
			if ( Services::WpGeneral()->isAjax() ) {
				throw $sare;
			}
			$response = $this->action( Actions\Render\PluginAdminPages\PageSecurityAdminRestricted::class, $data );
		}
		catch ( Exceptions\InvalidActionNonceException $iane ) {
			if ( Services::WpGeneral()->isAjax() ) {
				throw $iane;
			}
			wp_die( sprintf( 'Unexpected data. Please try again. Action Slug: "%s"; Data: "%s"', $classOrSlug, var_export( $data, true ) ) );
		}

		$adapter->adapt( $response );
		return $response;
	}

	/**
	 * This is an alias for calling the Render action directly
	 */
	public function render( string $classOrSlug, array $data = [] ) :string {
		try {
			$output = $this->action(
				Actions\Render::class,
				[
					'render_action_slug' => $classOrSlug,
					'render_action_data' => $data,
				]
			)->action_response_data[ 'render_output' ];
		}
		catch ( Exceptions\SecurityAdminRequiredException $e ) {
//			error_log( 'render::SecurityAdminRequiredException: '.$classOrSlug );
			$output = self::con()->action_router->render( PageSecurityAdminRestricted::class );
		}
		catch ( Exceptions\UserAuthRequiredException $uare ) {
//			error_log( 'render::UserAuthRequiredException: '.$classOrSlug );
			$output = '';
		}
		catch ( Exceptions\ActionException $e ) {
//			error_log( 'render::ActionException: '.$classOrSlug.' '.$e->getMessage() );
			$output = $e->getMessage();
		}

		return $output;
	}
}