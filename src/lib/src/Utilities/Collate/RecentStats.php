<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
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
	 * @var EventsDB\EntryVO[]
	 */
	private static $recentEvents;

	public function getRecentlyBlockedIPs() :array {
		if ( !isset( self::$recentlyBlocked ) ) {
			$loader = ( new LoadIpRules() )->setMod( $this->getCon()->getModule_IPs() );
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
			$loader = ( new LoadIpRules() )->setMod( $this->getCon()->getModule_IPs() );
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
		return self::$recentUserSessions ?? self::$recentUserSessions = ( new FindSessions() )->mostRecent() ;
	}

	public function getRecentEvents() :array {
		/** @var EventsDB\Select $select */
		if ( !isset( self::$recentEvents ) ) {
			$select = $this->getCon()->getModule_Events()->getDbHandler_Events()->getQuerySelector();
			self::$recentEvents = $select->getLatestForAllEvents();
		}
		return self::$recentEvents;
	}
}
