<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockCrowdsec;

class IpAutoUnblockCrowdsecVisitor extends IpAutoUnblockBase {

	use Traits\AuthNotRequired;

	public const SLUG = 'ip_auto_unblock_crowdsec_visitor';

	protected function getAutoUnblockerClass() :string {
		return AutoUnblockCrowdsec::class;
	}
}