<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Core;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 18.4.1
 */
class Databases {

	use ModConsumer;

	private $dbHandlers = [];

	/**
	 * @return string[]
	 */
	protected function getDbHandlerClasses() :array {
		$c = $this->mod()->opts()->getDef( 'db_handler_classes' );
		return \is_array( $c ) ? $c : [];
	}

	/**
	 * @return Core\Databases\Base\Handler|mixed|null
	 * @throws \Exception
	 * @deprecated 18.4.1
	 */
	public function loadDbH( string $dbKey, bool $reload = false ) {
		$req = Services::Request();
		$con = self::con();

		if ( $con->db_con !== null ) {
			return $con->db_con->loadDbH( $dbKey );
		}

		$dbh = $this->dbHandlers[ $dbKey ] ?? null;

		if ( $reload || empty( $dbh ) ) {

			$dbDef = $this->opts()->getDef( 'db_table_'.$dbKey );
			if ( empty( $dbDef ) ) {
				throw new \Exception( sprintf( 'DB Definition for key (%s) is empty', $dbKey ) );
			}

			$dbClasses = $this->getDbHandlerClasses();
			if ( !isset( $dbClasses[ $dbKey ] ) ) {
				throw new \Exception( sprintf( 'DB Handler for key (%s) is not valid', $dbKey ) );
			}

			$dbClass = $dbClasses[ $dbKey ];
			if ( !\class_exists( $dbClass ) ) {
				throw new \Exception( sprintf( 'DB Handler Class for key (%s) is not valid', $dbKey ) );
			}

			$modPlugin = $con->getModule_Plugin();
			$dbDef[ 'table_prefix' ] = $con->getPluginPrefix( '_' );
			/** @var Core\Databases\Base\Handler|mixed $dbh */
			$dbh = new $dbClass( $dbDef );
			$dbh->use_table_ready_cache = $modPlugin->getActivateLength() > Core\Databases\Common\TableReadyCache::READY_LIFETIME
										  && ( $req->ts() - $modPlugin->getTracking()->last_upgrade_at > 10 );
			$dbh->execute();

			$this->dbHandlers[ $dbKey ] = $dbh;
		}

		return $dbh;
	}
}