<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRuleRecord,
	LoadIpRules,
	Ops\Handler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RecentStats {
	use PluginControllerConsumer;

	/**
	 * @var IpRuleRecord[]
	 */
	private static ?array $recentlyBlocked = null;

	/**
	 * @var IpRuleRecord[]
	 */
	private static ?array $recentlyOffended= null ;

	public function getRecentlyBlockedIPs(): array {
		if ( !isset( self::$recentlyBlocked ) ) {
			$loader = new LoadIpRules();
			$loader->order_by = 'blocked_at';
			$loader->order_dir = 'DESC';
			$loader->limit = 10;
			$loader->wheres = [
				"`ir`.`blocked_at`>'0'",
				sprintf( "`ir`.`type`='%s'", Handler::T_AUTO_BLOCK ),
			];
			self::$recentlyBlocked = $loader->select();
		}
		return self::$recentlyBlocked;
	}

	public function getRecentlyOffendedIPs(): array {
		if ( !isset( self::$recentlyOffended ) ) {
			$loader = new LoadIpRules();
			$loader->order_by = 'last_access_at';
			$loader->order_dir = 'DESC';
			$loader->limit = 10;
			$loader->wheres = [
				"`ir`.`blocked_at`='0'",
				sprintf( "`ir`.`type`='%s'", Handler::T_AUTO_BLOCK ),
			];
			self::$recentlyOffended = $loader->select();
		}
		return self::$recentlyOffended;
	}
}
