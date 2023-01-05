<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Core;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Databases {

	use ModConsumer;

	private $dbHandlers = [];

	/**
	 * @return string[]
	 */
	protected function getDbHandlerClasses() :array {
		$c = $this->getMod()->getOptions()->getDef( 'db_handler_classes' );
		return is_array( $c ) ? $c : [];
	}

	/**
	 * @return Core\Databases\Base\Handler[]
	 * @throws \Exception
	 */
	public function loadAllDbHandlers( bool $reload = false ) :array {
		foreach ( array_keys( $this->getDbHandlerClasses() ) as $dbKey ) {
			$this->loadDbH( $dbKey, $reload );
		}
		return $this->dbHandlers;
	}

	/**
	 * @return Core\Databases\Base\Handler|mixed|null
	 * @throws \Exception
	 */
	public function loadDbH( string $dbKey, bool $reload = false ) {
		$dbh = $this->dbHandlers[ $dbKey ] ?? null;

		if ( $reload || empty( $dbh ) ) {

			$dbDef = $this->getOptions()->getDef( 'db_table_'.$dbKey );
			if ( empty( $dbDef ) ) {
				throw new \Exception( sprintf( 'DB Definition for key (%s) is empty', $dbKey ) );
			}

			$dbClasses = $this->getDbHandlerClasses();
			if ( !isset( $dbClasses[ $dbKey ] ) ) {
				throw new \Exception( sprintf( 'DB Handler for key (%s) is not valid', $dbKey ) );
			}

			$dbClass = $dbClasses[ $dbKey ];
			if ( !class_exists( $dbClass ) ) {
				throw new \Exception( sprintf( 'DB Handler Class for key (%s) is not valid', $dbKey ) );
			}

			$dbDef[ 'table_prefix' ] = $this->getCon()->getPluginPrefix( '_' );
			/** @var Core\Databases\Base\Handler|mixed $dbh */
			$dbh = new $dbClass( $dbDef );
			$dbh->execute();

			$this->dbHandlers[ $dbKey ] = $dbh;
		}

		return $dbh;
	}
}