<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockMagicLink;

class IpAutoUnblockShieldUserLinkVerify extends IpAutoUnblockBase {

	use Traits\AnyUserAuthRequired;

	public const SLUG = 'ip_auto_unblock_shield_user_link_verify';

	protected function getAutoUnblockerClass() :string {
		return AutoUnblockMagicLink::class;
	}
}