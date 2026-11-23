<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockMagicLink;

class IpAutoUnblockShieldUserLinkVerify extends IpAutoUnblockBase {

	use Traits\AnyUserAuthRequired;

	public const SLUG = 'ip_auto_unblock_shield_user_link_verify';

	/**
	 * The link is emailed to the user, who may have logged in again by the time they open it.
	 */
	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE_ANY_SESSION,
		];
	}

	protected function getAutoUnblockerClass() :string {
		return AutoUnblockMagicLink::class;
	}
}