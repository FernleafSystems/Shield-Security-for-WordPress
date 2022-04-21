<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;

class RecentStats {

	use PluginControllerConsumer;

	/**
	 * @var EntryVO[]
	 */
	private static $recentlyBlocked;

	/**
	 * @var EntryVO[]
	 */
	private static $recentlyOffended;

	/**
	 * @var array[]
	 */
	private static $recentUserSessions;

	public function getRecentlyBlockedIPs() :array {
		if ( !isset( self::$recentlyBlocked ) ) {
			/** @var Select $sel */
			$sel = $this->getCon()->getModule_IPs()->getDbHandler_IPs()->getQuerySelector();
			self::$recentlyBlocked = $sel->filterByBlocked( true )
										 ->setOrderBy( 'blocked_at' )
										 ->setLimit( 10 )
										 ->query();
		}
		return is_array( self::$recentlyBlocked ) ? self::$recentlyBlocked : [];
	}

	public function getRecentlyOffendedIPs() :array {
		if ( !isset( self::$recentlyOffended ) ) {
			/** @var Select $sel */
			$sel = $this->getCon()->getModule_IPs()->getDbHandler_IPs()->getQuerySelector();
			self::$recentlyOffended = $sel->filterByBlocked( false )
										  ->filterByList( ModCon::LIST_AUTO_BLACK )
										  ->setOrderBy( 'last_access_at' )
										  ->setLimit( 10 )
										  ->query();
		}
		return is_array( self::$recentlyOffended ) ? self::$recentlyOffended : [];
	}

	public function getRecentUserSessions() :array {
		if ( !isset( self::$recentUserSessions ) ) {
			self::$recentUserSessions = ( new FindSessions() )
				->setMod( $this->getCon()->getModule_UserManagement() )
				->mostRecent();
		}
		return is_array( self::$recentUserSessions ) ? self::$recentUserSessions : [];
	}
}
