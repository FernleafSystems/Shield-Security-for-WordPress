<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ActionRoutingController extends ExecOnceModConsumer {

	public const ACTION_AJAX = 1;
	public const ACTION_SHIELD = 2;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$this->captureRedirects();

		( new CaptureShieldAction() )
			->setMod( $this->getMod() )
			->execute();
		( new CaptureAjaxAction() )
			->setMod( $this->getMod() )
			->execute();
	}

	/**
	 * @throws Exceptions\ActionDoesNotExistException
	 * @throws Exceptions\ActionException
	 * @throws Exceptions\ActionTypeDoesNotExistException
	 */
	public function action( string $slug = '', array $data = [], int $type = self::ACTION_SHIELD ) :ActionResponse {

		switch ( $type ) {
			case self::ACTION_AJAX:
				$adapter = new ResponseAdapter\AjaxResponseAdapter();
				break;
			case self::ACTION_SHIELD:
				$adapter = new ResponseAdapter\ShieldActionResponseAdapter();
				break;
			default:
				throw new Exceptions\ActionTypeDoesNotExistException( $type );
		}

		try {
			$actionResponse = ( new ActionProcessor() )
				->setMod( $this->getMod() )
				->processAction( $slug, $data );
		}
		catch ( Exceptions\InvalidActionNonceException $e ) {
			wp_die( sprintf( 'Unexpected data. Please try again. Action Slug: "%s"; Data: "%s"', $slug, var_export( $data, true ) ) );
		}

		$adapter->setMod( $this->getMod() )->adapt( $actionResponse );
		return $actionResponse;
	}

	/**
	 * This is an alias for calling the Render action directly
	 */
	public function render( string $slug, array $data = [] ) :string {
		try {
			$output = $this->action(
				Actions\Render::SLUG,
				[
					'render_action_slug' => $slug,
					'render_action_data' => $data,
				]
			)->action_response_data[ 'render_output' ];
		}
		catch ( Exceptions\ActionException $e ) {
			$output = $e->getMessage();
		}

		return $output;
	}

	private function captureRedirects() {
		$con = $this->getCon();
		$urls = $con->plugin_urls;
		$req = Services::Request();

		if ( is_admin() && !Services::WpGeneral()->isAjax() ) {

			$redirectTo = null;

			if ( $con->isModulePage() ) {
				$page = (string)$req->query( 'page' );
				$navID = $req->query( Constants::NAV_ID );
				$subNavID = $req->query( Constants::NAV_SUB_ID );

				if ( empty( $navID ) ) {
					$pseudoNavID = explode( '-', $page )[ 2 ] ?? '';
					$redirectTo = $urls->isValidNav( $pseudoNavID ) ? $urls->adminTopNav( $pseudoNavID ) : $urls->adminHome();
				}
				elseif ( $navID === PluginURLs::NAV_OPTIONS_CONFIG && empty( $subNavID ) ) {
					$redirectTo = $urls->modCfg( $con->getModule_Plugin() );
				}
			}
			elseif ( $con->getModule_Plugin()->getActivateLength() < 5 ) {
				$redirectTo = $urls->adminTopNav( PluginURLs::NAV_WIZARD );
			}

			if ( !empty( $redirectTo ) ) {
				Services::Response()->redirect( $redirectTo, [], true, false );
			}
		}
	}
}