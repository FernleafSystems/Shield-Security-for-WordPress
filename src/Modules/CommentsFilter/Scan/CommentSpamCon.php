<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CommentSpamCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions;
	}

	protected function run() {
		add_filter( 'init', [ $this, 'onWpInit' ], 9 );
	}

	public function onWpInit() {
		$trustTest = new IsEmailTrusted();
		$user = Services::WpUsers()->getCurrentWpUser();
		if ( empty( $user ) || ( !$trustTest->roleTrusted( $user ) && !$trustTest->emailTrusted( $user->user_email ) ) ) {
			if ( Services::Request()->isPost() ) {
				( new Scanner() )->execute();
				add_filter( 'comment_notification_recipients', [ $this, 'clearCommentNotificationEmail' ], 100 );
			}
		}

		add_filter( 'pre_comment_user_ip', [ $this, 'setCorrectCommentIP' ], 10, 0 );

		( new CommentAdditiveCleaner() )->execute();
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

	public function setCorrectCommentIP() :string {
		return Services::Request()->ip();
	}
}