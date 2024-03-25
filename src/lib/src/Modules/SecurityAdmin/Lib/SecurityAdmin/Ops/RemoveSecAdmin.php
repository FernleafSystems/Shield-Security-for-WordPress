<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
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

		$con->email_con->sendEmailWithWrap(
			$con->comps->opts_lookup->getReportEmail(),
			__( 'Please Confirm Security Admin Removal', 'wp-simple-firewall' ),
			[
				sprintf( __( 'A WordPress user (%s) has requested to remove the Security Admin restriction.', 'wp-simple-firewall' ),
					Services::WpUsers()->getCurrentWpUsername() ).'  '.
				__( 'The purpose of this email is to confirm this action.', 'wp-simple-firewall' ),
				__( 'Please click the link below to confirm the removal of all Security Admin restrictions.', 'wp-simple-firewall' ),
				'',
				'<strong>'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
					__( 'This link must be opened in the same browser that was used to make this original request.', 'wp-simple-firewall' )
				).'</strong>',
				'',
				sprintf( '%s: %s', __( 'Confirmation link', 'wp-simple-firewall' ), $confirmationHref ),
				'',
				__( "Please understand that to reinstate the Security Admin features, you'll need to provide a new Security Admin PIN.", 'wp-simple-firewall' ),
				'',
				__( "Thank you.", 'wp-simple-firewall' )
			]
		);
	}

	private function sendNotificationEmail() {
		self::con()->email_con->sendEmailWithWrap(
			self::con()->comps->opts_lookup->getReportEmail(),
			__( 'Security Admin restrictions have been removed', 'wp-simple-firewall' ),
			[
				__( 'This is an email notification to inform you that the Security Admin restriction has been removed.', 'wp-simple-firewall' ),
				__( 'This was done using a confirmation email sent to the Security Administrator email address.', 'wp-simple-firewall' ),
				__( 'All restrictions imposed by the Security Admin module have been lifted.', 'wp-simple-firewall' ),
				'',
				__( "Please understand that to reinstate the Security Admin features, you'll need to provide a new Security Admin PIN.", 'wp-simple-firewall' ),
				'',
				__( "Thank you.", 'wp-simple-firewall' )
			]
		);
	}
}