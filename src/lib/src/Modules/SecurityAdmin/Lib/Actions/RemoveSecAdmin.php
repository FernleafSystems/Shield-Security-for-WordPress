<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
use FernleafSystems\Wordpress\Services\Services;

class RemoveSecAdmin {

	use ModConsumer;

	/**
	 */
	public function remove() {
		/** @var SecurityAdmin\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->hasAccessKey() ) {
			$oOpts->clearSecurityAdminKey();
			$this->getMod()->saveModOptions();
			$this->sendNotificationEmail();
		}
	}

	public function sendConfirmationEmail() {
		/** @var \ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oMod */
		$oMod = $this->getMod();
		$sEmail = $oMod->getPluginDefaultRecipientAddress();
		if ( !Services::Data()->validEmail( $sEmail ) ) {
			$sEmail = Services::WpGeneral()->getSiteAdminEmail();
		}

		$aMessage = [
			sprintf( __( 'A WordPress user (%s) has requested to remove the Security Admin restriction.', 'wp-simple-firewall' ),
				Services::WpUsers()->getCurrentWpUsername() ).'  '.
			__( 'The purpose of this email is to confirm this action.', 'wp-simple-firewall' ),
			__( 'Please click the link below to confirm the removal of the Security Admin restriction.', 'wp-simple-firewall' ),
			'',
			'<strong>'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
				__( 'This link must be opened in the same browser that was used to make this original request.', 'wp-simple-firewall' )
			).'</strong>',
			'',
			sprintf( '%s: %s', __( 'Confirmation link', 'wp-simple-firewall' ),
				$oMod->buildAdminActionNonceUrl( 'remove_secadmin_confirm' ) ),
			'',
			__( "Please understand that to reinstate the Security Admin features, you'll need to provide a new Security Admin password.", 'wp-simple-firewall' ),
			'',
			__( "Thank you.", 'wp-simple-firewall' )
		];

		$sEmailSubject = __( 'Please Confirm Security Admin Removal', 'wp-simple-firewall' );
		return $this->getMod()
					->getEmailProcessor()
					->sendEmailWithWrap( $sEmail, $sEmailSubject, $aMessage );
	}

	private function sendNotificationEmail() {
		$sEmail = $this->getMod()->getPluginDefaultRecipientAddress();
		if ( !Services::Data()->validEmail( $sEmail ) ) {
			$sEmail = Services::WpGeneral()->getSiteAdminEmail();
		}

		$aMessage = [
			__( 'This is an email notification to inform you that the Security Admin restriction has been removed.', 'wp-simple-firewall' ),
			__( 'This was done using a confirmation email sent to the Security Administrator email address.', 'wp-simple-firewall' ),
			__( 'All restrictions imposed by the Security Admin module have been lifted.', 'wp-simple-firewall' ),
			'',
			__( "Please understand that to reinstate the Security Admin features, you'll need to provide a new Security Admin password.", 'wp-simple-firewall' ),
			'',
			__( "Thank you.", 'wp-simple-firewall' )
		];

		$sEmailSubject = __( 'Security Admin restrictions have been removed', 'wp-simple-firewall' );
		return $this->getMod()
					->getEmailProcessor()
					->sendEmailWithWrap( $sEmail, $sEmailSubject, $aMessage );
	}
}