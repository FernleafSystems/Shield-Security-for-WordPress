<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Definition;

class DismissAdminNotice extends BaseAction {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'dismiss_admin_notice';

	protected function exec() {
		$this->handleDismissal( $this->action_data, $this->response() );
	}

	private function handleDismissal( array $actionData, ActionResponse $response ) :ActionResponse {
		$noticeID = sanitize_key( $actionData[ 'notice_id' ] ?? '' );
		if ( !empty( $noticeID ) ) {
			foreach ( self::con()->admin_notices->getAdminNotices() as $notice ) {
				if ( $noticeID === $notice->id ) {
					self::con()->admin_notices->setNoticeDismissed( $notice );
					$response->action_response_data = [
						'success'   => true,
						'message'   => __( 'Admin notice dismissed', 'wp-simple-firewall' ),
						'notice_id' => $notice->id,
					];
					break;
				}
			}
		}
		return $response;
	}

	public static function simpleDefinition() :Definition {
		return new Definition(
			static::SLUG,
			function ( array $actionData, ActionResponse $response ) {
				return ( new self() )->handleDismissal( $actionData, $response );
			},
			[
				'required_data' => [ 'notice_id' ],
				'policies'      => [
					Definition::POLICY_REQUIRE_SECURITY_ADMIN => false,
				],
			]
		);
	}
}
