<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockMagicLink;
use FernleafSystems\Wordpress\Services\Services;

class IpAutoUnblockShieldUserLinkVerify extends IpAutoUnblockShieldVisitor {

	const SLUG = 'ip_auto_unblock_shield_user_link_verify';
	const PATTERN = self::SLUG.'-[a-f\d.:]+';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$unBlocker = ( new AutoUnblockMagicLink() )->setMod( $this->primary_mod );
		if ( $unBlocker->canRunAutoUnblockProcess() && $unBlocker->processUnblockLink() ) {
			Services::Response()->redirectToHome();
		}
	}
}