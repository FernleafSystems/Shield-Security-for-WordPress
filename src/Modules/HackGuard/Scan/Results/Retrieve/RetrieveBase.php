<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @property string[] $wheres
 */
abstract class RetrieveBase extends DynPropertiesClass {

	use PluginControllerConsumer;
	use ScanControllerConsumer;

	public const ABBR_RESULTITEMMETA = '`rim`';

	protected $additionalWheres = [];

	abstract public function buildQuery( array $selectFields = [] ) :string;

	abstract protected function getBaseQuery( bool $joinWithResultMeta = false ) :string;

	public function getAdditionalWheres() :array {
		return \is_array( $this->additionalWheres ) ? $this->additionalWheres : [];
	}

	public function getWheres() :array {
		return \array_filter( \array_map( '\trim', \is_array( $this->wheres ) ? $this->wheres : [] ) );
	}

	/**
	 * @return $this
	 */
	public function addWheres( array $wheres, bool $merge = true ) {
		$wheres = $this->sanitizeWheres( $wheres );
		$this->wheres = $merge ? \array_merge( $this->getWheres(), $wheres ) : $wheres;
		return $this;
	}

	/**
	 * @template T
	 * @param callable():T $callback
	 * @return T
	 */
	protected function withMergedWheres( array $wheres, callable $callback ) {
		$originalWheres = $this->getWheres();
		$this->wheres = \array_merge( $originalWheres, $this->sanitizeWheres( $wheres ) );

		try {
			return $callback();
		}
		finally {
			$this->wheres = $originalWheres;
		}
	}

	private function sanitizeWheres( array $wheres ) :array {
		return \array_filter( \array_map( '\trim', $wheres ) );
	}
}
