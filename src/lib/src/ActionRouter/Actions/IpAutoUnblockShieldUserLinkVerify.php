<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockMagicLink;
use FernleafSystems\Wordpress\Services\Services;

class IpAutoUnblockShieldUserLinkVerify extends IpAutoUnblockShieldVisitor {

	public const SLUG = 'ip_auto_unblock_shield_user_link_verify';
	public const PATTERN = self::SLUG.'-[a-f\d.:]+';

	// TODO: produce an error response to tell user why it failed
	protected function exec() {
		$unBlocker = ( new AutoUnblockMagicLink() )->setMod( $this->primary_mod );
		if ( $unBlocker->canRunAutoUnblockProcess() && $unBlocker->processUnblockLink() ) {
			Services::Response()->redirectToHome();
		}
	}
}