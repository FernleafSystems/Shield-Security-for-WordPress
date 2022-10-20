<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockMagicLink;

class IpAutoUnblockShieldUserLinkRequest extends IpsBase {

	const SLUG = 'ip_auto_unblock_shield_user_link_request';

	protected function exec() {
		$unBlocker = ( new AutoUnblockMagicLink() )->setMod( $this->primary_mod );
		if ( $unBlocker->canRunAutoUnblockProcess() ) {
			try {
				$unBlocker->processEmailSend();
				$this->response()->action_response_data = [
					'success' => true,
					'message' => 'Email sent',
				];
			}
			catch ( \Exception $e ) {
				throw new ActionException( $e->getMessage() );
			}
		}
	}
}