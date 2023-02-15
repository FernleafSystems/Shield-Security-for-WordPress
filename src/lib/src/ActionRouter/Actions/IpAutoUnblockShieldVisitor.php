<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockVisitor;

class IpAutoUnblockShieldVisitor extends IpAutoUnblockBase {

	use Traits\AuthNotRequired;

	public const SLUG = 'ip_auto_unblock_shield_visitor';

	protected function getAutoUnblockerClass() :string {
		return AutoUnblockVisitor::class;
	}
}