<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\UnblockMagicLink;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockMagicLink extends BaseAutoUnblockShield {

	public function isUnblockAvailable() :bool {
		return \in_array( 'email', self::con()->opts->optGet( 'user_auto_recover' ) ) && parent::isUnblockAvailable();
	}

	protected function getUnblockMethodName() :string {
		return 'Magic Link';
	}

	/**
	 * @throws \Exception
	 */
	public function processEmailSend() {
		$con = self::con();
		$user = Services::WpUsers()->getCurrentWpUser();
		if ( !$user instanceof \WP_User ) {
			throw new \Exception( 'There is no user currently logged-in.' );
		}

		$reqIP = Services::Request()->request( 'ip' );
		if ( empty( $reqIP ) || !Services::IP()->IpIn( $reqIP, [ $con->this_req->ip ] ) ) {
			throw new \Exception( 'IP does not match.' );
		}

		$con->email_con->sendVO(
			EmailVO::Factory(
				$user->user_email,
				__( 'Automatic IP Unblock Request', 'wp-simple-firewall' ),
				$con->action_router->render( UnblockMagicLink::SLUG, [
					'home_url' => Services::WpGeneral()->getHomeUrl(),
					'ip'       => $con->this_req->ip,
					'user_id'  => $user->ID,
				] )
			)
		);
	}
}