<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\AdminPage;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class ActionRoutingController extends ExecOnceModConsumer {

	const ACTION_RENDER = 0;
	const ACTION_AJAX = 1;
	const ACTION_SHIELD = 2;
	const NAV_ID = 'nav';
	const NAV_SUB_ID = 'nav_sub';

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$this->redirects();
		add_action( 'init', [ $this, 'onWpInit' ], 0 );
	}

	public function onWpInit() {
		if ( is_admin() || is_network_admin() ) {
			( new AdminPage() )->setMod( $this->getMod() )->execute();
		}
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
			case self::ACTION_RENDER:
				$adapter = new ResponseAdapter\RenderResponseAdapter();
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
			wp_die( 'Unexpected data. Please try again.' );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}

		$adapter->setMod( $this->getMod() )->adapt( $actionResponse );
		return $actionResponse;
	}

	/**
	 * @throws Exceptions\ActionDoesNotExistException
	 * @throws Exceptions\ActionException
	 * @throws Exceptions\ActionTypeDoesNotExistException
	 */
	public function render( string $slug, array $data = [] ) :ActionResponse {
		return $this->action( $slug, $data, self::ACTION_RENDER );
	}

	private function redirects() {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$redirectTo = null;

		if ( !Services::WpGeneral()->isAjax() && is_admin() ) {

			$reqPage = (string)$req->query( 'page' );
			if ( $reqPage === $con->prefix()
				 //				 || ( $mod->getAdminPage()->isCurrentPage() && empty( $mod->getCurrentInsightsPage() ) )
				 || ( in_array( $mod->getCurrentInsightsPage(), [ 'dashboard', 'index' ] ) )
			) {
				$redirectTo = $con->getPluginUrl_DashboardHome();
			}
			elseif ( $con->isModulePage() ) {

				// 'insights'
				if ( $reqPage === $mod->getModSlug() ) {
					if ( empty($req->query( self::NAV_ID )) ) {
						$redirectTo = $mod->getUrl_SubInsightsPage( Constants::ADMIN_PAGE_OVERVIEW );
					}
					elseif ( $req->query( self::NAV_ID ) === Constants::ADMIN_PAGE_CONFIG && empty( $req->query( self::NAV_SUB_ID ) ) ) {
						$redirectTo = $mod->getUrl_SubInsightsPage( Constants::ADMIN_PAGE_CONFIG, 'plugin' );
					}
				}
				elseif ( preg_match( sprintf( '#^%s-redirect-([a-z_\-]+)$#', $con->prefix() ), $reqPage, $matches ) ) {
					// Custom wp admin menu items redirect:
					$redirectTo = $mod->getUrl_SubInsightsPage( $matches[ 1 ] );
				}
				else {
					die( 'REDIRECT HERE' );
					$this->redirectToInsightsSubPage();
				}
			}
			elseif ( $con->getModule_Plugin()->getActivateLength() < 5 ) {
				$redirectTo = $mod->getUrl_SubInsightsPage( 'merlin' );
			}
		}

		if ( !empty( $redirectTo ) ) {
			Services::Response()->redirect( $redirectTo, [], true, false );
		}
	}
}