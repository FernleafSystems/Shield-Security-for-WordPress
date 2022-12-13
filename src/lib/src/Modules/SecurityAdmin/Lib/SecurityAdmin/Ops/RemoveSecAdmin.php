<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\SecurityAdminRemoveByEmail;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
use FernleafSystems\Wordpress\Services\Services;

class RemoveSecAdmin {

	use ModConsumer;

	public function remove() {
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->hasSecurityPIN() ) {
			$this->getCon()->this_req->is_security_admin = true;
			$opts->clearSecurityAdminKey();
			$this->getMod()->saveModOptions();
			$this->sendNotificationEmail();
		}
	}

	public function sendConfirmationEmail() {
		$confirmationHref = $this->getCon()->plugin_urls->noncedPluginAction(
			SecurityAdminRemoveByEmail::SLUG,
			Services::WpGeneral()->getAdminUrl()
		);
		/** @var SecurityAdmin\ModCon $mod */
		$mod = $this->getMod();
		$mod->getEmailProcessor()
			->sendEmailWithWrap(
				$mod->getPluginReportEmail(),
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
		$this->getMod()
			 ->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $this->getMod()->getPluginReportEmail(),
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