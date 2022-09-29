<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\{
	Actions,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class BlockRequestCrowdsec extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->getCon()->this_req->is_ip_blocked_crowdsec;
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$csRecords = ( new IpRuleStatus( $this->getCon()->this_req->ip ) )
			->setMod( $this->getMod() )
			->getRulesForCrowdsec();
		foreach ( $csRecords as $record ) {
			/** @var IpRulesDB\Update $updater */
			$updater = $mod->getDbH_IPRules()->getQueryUpdater();
			$updater->updateLastAccessAt( $record );
		}

		do_action( 'shield/maybe_intercept_block_crowdsec' );

		$this->getCon()->fireEvent( 'conn_kill_crowdsec' );

		try {
			$this->getCon()
				 ->getModule_Insights()
				 ->getActionRouter()
				 ->action( Actions\FullPageDisplay\DisplayBlockPage::SLUG, [
					 'render_slug' => Actions\Render\FullPage\Block\BlockIpAddressCrowdsec::SLUG
				 ] );
		}
		catch ( ActionException $e ) {
			error_log( __CLASS__.': '.$e->getMessage() );
		}
	}
}