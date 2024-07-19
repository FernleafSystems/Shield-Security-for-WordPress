<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRuleRecord,
	LoadIpRules,
	Ops\Handler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;

class RecentStats {

	use PluginControllerConsumer;

	/**
	 * @var IpRuleRecord[]
	 */
	private static $recentlyBlocked;

	/**
	 * @var IpRuleRecord[]
	 */
	private static $recentlyOffended;

	/**
	 * @var array[]
	 */
	private static $recentUserSessions;

	/**
	 * @var EventsDB\Record[]
	 */
	private static $recentEvents;

	public function getRecentlyBlockedIPs() :array {
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

	public function getRecentlyOffendedIPs() :array {
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

	public function getRecentUserSessions() :array {
		return self::$recentUserSessions ?? self::$recentUserSessions = ( new FindSessions() )->mostRecent();
	}

	public function getRecentEvents() :array {
		if ( !isset( self::$recentEvents ) ) {
			/** @var EventsDB\Select $select */
			$select = self::con()->db_con->events->getQuerySelector();
			self::$recentEvents = $select->getLatestForAllEvents();
		}
		return self::$recentEvents;
	}
}
