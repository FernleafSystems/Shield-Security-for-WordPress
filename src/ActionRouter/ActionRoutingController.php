<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActionRoutingController {

	use ExecOnce;
	use PluginControllerConsumer;

	public const ACTION_AJAX = 1;
	public const ACTION_SHIELD = 2;
	public const ACTION_REST = 3;

	private ?ActionExecutor $executor = null;

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
	public function action( string $classOrSlug, array $data = [], int $type = self::ACTION_SHIELD ) :RoutedResponse {
		return $this->getExecutor()->execute( $classOrSlug, $data, $type );
	}

	/**
	 * @internal Transition helper so capture classes can avoid routing recursion.
	 */
	public function executor() :ActionExecutor {
		return $this->getExecutor();
	}

	/**
	 * This is an alias for calling the Render action directly
	 */
	public function render( string $classOrSlug, array $data = [] ) :string {
		try {
			$payload = $this->action(
				Actions\Render::class,
				[
					'render_action_slug' => $classOrSlug,
					'render_action_data' => $data,
				]
			)->payload();
			$output = $payload[ 'render_output' ] ?? '';
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

	private function getExecutor() :ActionExecutor {
		return $this->executor ??= new ActionExecutor();
	}
}