<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	public function onWpInit() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$WPU = Services::WpUsers();

		$commentsFilterBypass = $this->getCon()->this_req->request_bypasses_all_restrictions ||
								( $WPU->isUserLoggedIn() &&
								  ( new Scan\IsEmailTrusted() )->trusted(
									  $WPU->getCurrentWpUser()->user_email,
									  $opts->getApprovedMinimum(),
									  $opts->getTrustedRoles()
								  ) );

		if ( !$commentsFilterBypass ) {

			( new Scan\CommentAdditiveCleaner() )
				->setMod( $this->getMod() )
				->execute();

			if ( Services::Request()->isPost() ) {
				( new Scan\Scanner() )
					->setMod( $this->getMod() )
					->execute();
				add_filter( 'comment_notification_recipients', [ $this, 'clearCommentNotificationEmail' ], 100, 1 );
			}
		}
	}

	/**
	 * When you set a new comment as anything but 'spam' a notification email is sent to the post author.
	 * We suppress this for when we mark as trash by emptying the email notifications list.
	 * @param array $emails
	 * @return array
	 */
	public function clearCommentNotificationEmail( $emails ) {
		$status = apply_filters( $this->getCon()->prefix( 'cf_status' ), '' );
		return in_array( $status, [ 'reject', 'trash' ] ) ? [] : $emails;
	}
}