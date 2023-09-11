<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Wordpress\Plugin\Core\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class DbCon {

	use PluginControllerConsumer;

	/**
	 * @var ?|array
	 */
	private $dbHandlers = null;

	/**
	 * @return array[]
	 */
	public function getHandlers() :array {
		if ( $this->dbHandlers === null ) {
			$this->dbHandlers = [];
			foreach ( self::con()->modules as $mod ) {
				$classes = $mod->opts()->getDef( 'db_handler_classes' );
				foreach ( \is_array( $classes ) ? $classes : [] as $dbKey => $dbClass ) {
					$this->dbHandlers[ $dbKey ] = [
						'class'   => $dbClass,
						'def'     => $mod->opts()->getDef( 'db_table_'.$dbKey ),
						'handler' => null,
					];
				}
			}
		}
		return $this->dbHandlers;
	}

	/**
	 * @return Databases\Base\Handler|mixed|null
	 * @throws \Exception
	 */
	public function loadDbH( string $dbKey, bool $reload = false ) {
		$req = Services::Request();
		$con = self::con();

		$dbh = $this->getHandlers()[ $dbKey ] ?? null;
		if ( empty( $dbh ) ) {
			throw new \Exception( sprintf( 'Invalid DBH Key %s', $dbKey ) );
		}

		// TODO parse the columns looking for foreign keys and init the table if 1 is found.

		if ( $reload || empty( $dbh[ 'handler' ] ) ) {

			if ( empty( $dbh[ 'class' ] ) ) {
				throw new \Exception( sprintf( 'DB Handler Class for key (%s) is not specified.', $dbKey ) );
			}
			if ( !\class_exists( $dbh[ 'class' ] ) ) {
				throw new \Exception( sprintf( 'DB Handler for key (%s) is not valid', $dbKey ) );
			}
			if ( empty( $dbh[ 'def' ] ) ) {
				throw new \Exception( sprintf( 'DB Definition for key (%s) is empty', $dbKey ) );
			}

			$dbDef = $dbh[ 'def' ];
			$dbDef[ 'table_prefix' ] = $con->getPluginPrefix( '_' );

			$modPlugin = $con->getModule_Plugin();
			/** @var Databases\Base\Handler|mixed $dbh */
			$dbh = new $dbh[ 'class' ]( $dbDef );
			$dbh->use_table_ready_cache = $modPlugin->getActivateLength() > Databases\Common\TableReadyCache::READY_LIFETIME
										  && ( $req->ts() - $modPlugin->getTracking()->last_upgrade_at > 10 );
			$dbh->execute();

			$this->dbHandlers[ $dbKey ][ 'handler' ] = $dbh;
		}

		return $this->dbHandlers[ $dbKey ][ 'handler' ];
	}
}