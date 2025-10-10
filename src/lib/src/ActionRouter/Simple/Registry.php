<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionDoesNotExistException;

class Registry {

	/**
	 * @var Definition[]
	 */
	private array $definitions = [];

	public function register( Definition $definition ) :void {
		$this->definitions[ $definition->slug() ] = $definition;
	}

	public function has( string $slug ) :bool {
		return isset( $this->definitions[ $slug ] );
	}

	/**
	 * @throws ActionDoesNotExistException
	 */
	public function get( string $slug ) :Definition {
		if ( !$this->has( $slug ) ) {
			throw new ActionDoesNotExistException( $slug );
		}
		return $this->definitions[ $slug ];
	}

	/**
	 * @return Definition[]
	 */
	public function all() :array {
		return $this->definitions;
	}
}
