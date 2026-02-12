<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\SecAdminRemoveConfirm;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\SecAdminRemoveNotice;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RemoveSecAdmin {

	use PluginControllerConsumer;

	public function remove( bool $quietly = false ) {
		if ( !empty( self::con()->comps->opts_lookup->getSecAdminPIN() ) ) {
			self::con()->this_req->is_security_admin = true;

			// If you delete the PIN, you also delete the sec admins. Prevents a lockout scenario.
			self::con()
				->opts
				->optSet( 'admin_access_key', '' )
				->optSet( 'sec_admin_users', [] )
				->store();

			( new ToggleSecAdminStatus() )->turnOff();

			// After removing Security Admin entirely, ensure flag remains true since protection is now disabled
			self::con()->this_req->is_security_admin = true;

			if ( !$quietly ) {
				$this->sendNotificationEmail();
			}
		}
	}

	public function sendConfirmationEmail() {
		$con = self::con();

		$confirmationHref = $con->plugin_urls->noncedPluginAction(
			SecurityAdminRemove::class,
			Services::WpGeneral()->getAdminUrl()
		);

		$con->email_con->sendVO(
			EmailVO::Factory(
				$con->comps->opts_lookup->getReportEmail(),
				__( 'Please Confirm Security Admin Removal', 'wp-simple-firewall' ),
				$con->action_router->render( SecAdminRemoveConfirm::class, [
					'username'          => Services::WpUsers()->getCurrentWpUsername(),
					'confirmation_href' => $confirmationHref,
				] )
			)
		);
	}

	private function sendNotificationEmail() {
		$con = self::con();
		$con->email_con->sendVO(
			EmailVO::Factory(
				$con->comps->opts_lookup->getReportEmail(),
				__( 'Security Admin restrictions have been removed', 'wp-simple-firewall' ),
				$con->action_router->render( SecAdminRemoveNotice::class )
			)
		);
	}
}
