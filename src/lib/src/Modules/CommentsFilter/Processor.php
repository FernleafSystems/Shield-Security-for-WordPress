<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Services\Services;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	public function onWpInit() {
		$WPU = Services::WpUsers();

		$bypass = self::con()->this_req->request_bypasses_all_restrictions;
		if ( !$bypass && $WPU->isUserLoggedIn() ) {
			$testTrustedUser = new Scan\IsEmailTrusted();
			$bypass = $testTrustedUser->roleTrusted( $WPU->getCurrentWpUser() )
					  || $testTrustedUser->emailTrusted( $WPU->getCurrentWpUser()->user_email );
		}

		if ( !$bypass ) {

			( new Scan\CommentAdditiveCleaner() )->execute();

			if ( Services::Request()->isPost() ) {
				( new Scan\Scanner() )->execute();
				add_filter( 'comment_notification_recipients', [ $this, 'clearCommentNotificationEmail' ], 100 );
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
		$status = apply_filters( self::con()->prefix( 'cf_status' ), '' );
		return \in_array( $status, [ 'reject', 'trash' ] ) ? [] : $emails;
	}
}