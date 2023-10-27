<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

class BlockRequestCrowdsec {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return self::con()->this_req->is_ip_blocked_crowdsec;
	}

	protected function run() {

		foreach ( ( new IpRuleStatus( self::con()->this_req->ip ) )->getRulesForCrowdsec() as $record ) {
			/** @var IpRulesDB\Update $updater */
			$updater = $this->mod()->getDbH_IPRules()->getQueryUpdater();
			$updater->updateLastAccessAt( $record );
		}

		do_action( 'shield/maybe_intercept_block_crowdsec' );

		self::con()->fireEvent( 'conn_kill_crowdsec' );

		try {
			self::con()->action_router->action( Actions\FullPageDisplay\DisplayBlockPage::class, [
				'render_slug' => Actions\Render\FullPage\Block\BlockIpAddressCrowdsec::SLUG
			] );
		}
		catch ( ActionException $e ) {
		}
	}
}