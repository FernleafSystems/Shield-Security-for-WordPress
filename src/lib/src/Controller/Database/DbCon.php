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
					$def = $mod->opts()->getDef( 'db_table_'.$dbKey );
					$this->dbHandlers[ $dbKey ] = [
						'name'    => $def[ 'name' ] ?? $dbKey,
						'class'   => $dbClass,
						'def'     => $def,
						'handler' => null,
					];
				}
			}
		}
		return $this->dbHandlers;
	}

	/**
	 * @return array[]
	 */
	public function loadAll() :array {
		foreach ( \array_keys( $this->getHandlers() ) as $dbhKey ) {
			try {
				$this->loadDbH( $dbhKey );
			}
			catch ( \Exception $exception ) {
			}
		}
		return $this->getHandlers();
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

			/**
			 * We need to ensure that any dependent (foreign key references) tables are initiated before
			 * attempting to initiate ourselves.
			 */
			$dbDef = $dbh[ 'def' ];
			foreach ( $dbDef[ 'cols_custom' ] as $colDef ) {
				if ( ( $colDef[ 'macro_type' ] ?? '' ) === Databases\Common\Types::MACROTYPE_FOREIGN_KEY_ID ) {
					$table = $colDef[ 'foreign_key' ][ 'ref_table' ];
					if ( \str_starts_with( $table, $con->getPluginPrefix( '_' ) ) ) {
						$this->loadDbH( \str_replace( $con->getPluginPrefix( '_' ).'_', '', $table ) );
					}
				}
			}

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