<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Examples;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DismissAdminNotice;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Definition;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Dispatcher;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Registry;

class DismissAdminNoticeExample {

	private Registry $registry;

	private Dispatcher $dispatcher;

	public function __construct( Registry $registry, Dispatcher $dispatcher ) {
		$this->registry = $registry;
		$this->dispatcher = $dispatcher;
	}

	public function registerClassDefinition() :void {
		$this->registry->register( DismissAdminNotice::simpleDefinition() );
	}

	public function dispatchClassDefinition( array $data ) :ActionResponse {
		return $this->dispatcher->dispatch(
			$this->registry->get( DismissAdminNotice::SLUG ),
			$data
		);
	}

	public function registerClosureDefinition() :void {
		$this->registry->register( new Definition(
			'dismiss_admin_notice_closure_demo',
			function ( array $actionData, ActionResponse $response ) {
				$noticeID = sanitize_key( $actionData[ 'notice_id' ] ?? '' );
				if ( !empty( $noticeID ) ) {
					$con = shield_security_get_plugin()->getController();
					foreach ( $con->admin_notices->getAdminNotices() as $notice ) {
						if ( $noticeID === $notice->id ) {
							$con->admin_notices->setNoticeDismissed( $notice );
							$response->action_response_data = [
								'success'   => true,
								'message'   => __( 'Admin notice dismissed (closure demo)', 'wp-simple-firewall' ),
								'notice_id' => $notice->id,
							];
							break;
						}
					}
				}
				return $response;
			},
			[
				'required_data' => [ 'notice_id' ],
				'policies'      => [
					Definition::POLICY_REQUIRE_SECURITY_ADMIN => false,
					Definition::POLICY_REQUIRE_NONCE          => true,
				],
			]
		) );
	}

	public function dispatchClosureDefinition( array $data ) :ActionResponse {
		return $this->dispatcher->dispatch(
			$this->registry->get( 'dismiss_admin_notice_closure_demo' ),
			$data
		);
	}
}
