<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockMagicLink;

class IpAutoUnblockShieldUserLinkRequest extends BaseAction {

	use Traits\AnyUserAuthRequired;
	use Traits\ByPassIpBlock;

	public const SLUG = 'ip_auto_unblock_shield_user_link_request';

	protected function exec() {
		$unBlocker = new AutoUnblockMagicLink();
		if ( $unBlocker->canRunAutoUnblockProcess() ) {
			try {
				$unBlocker->processEmailSend();
				$this->response()->action_response_data = [
					'success' => true,
					'message' => 'Please check your email for the unblocking link.',
				];
			}
			catch ( \Exception $e ) {
				throw new ActionException( $e->getMessage() );
			}
		}
	}

	protected function getMinimumUserAuthCapability() :string {
		return 'read';
	}
}