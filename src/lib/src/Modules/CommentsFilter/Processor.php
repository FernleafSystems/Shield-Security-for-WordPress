<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	public function onWpInit() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$oWpUsers = Services::WpUsers();

		$bLoadComProc = !$oWpUsers->isUserLoggedIn() ||
						!( new Scan\IsEmailTrusted() )->trusted(
							$oWpUsers->getCurrentWpUser()->user_email,
							$opts->getApprovedMinimum(),
							$opts->getTrustedRoles()
						);

		if ( $bLoadComProc ) {

			( new Forms\GoogleRecaptcha() )
				->setMod( $this->getMod() )
				->execute();

			if ( Services::Request()->isPost() ) {
				( new Scan\Scanner() )
					->setMod( $this->getMod() )
					->execute();
				add_filter( 'comment_notification_recipients', [ $this, 'clearCommentNotificationEmail' ], 100, 1 );
			}
			else {
				( new Forms\Gasp() )
					->setMod( $this->getMod() )
					->execute();
			}
		}
	}

	public function runHourlyCron() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isEnabledGaspCheck() && function_exists( 'delete_expired_transients' ) ) {
			delete_expired_transients(); // cleanup unused comment tokens
		}
	}

	/**
	 * When you set a new comment as anything but 'spam' a notification email is sent to the post author.
	 * We suppress this for when we mark as trash by emptying the email notifications list.
	 * @param array $aEmails
	 * @return array
	 */
	public function clearCommentNotificationEmail( $aEmails ) {
		$sStatus = apply_filters( $this->getCon()->prefix( 'cf_status' ), '' );
		if ( in_array( $sStatus, [ 'reject', 'trash' ] ) ) {
			$aEmails = [];
		}
		return $aEmails;
	}
}