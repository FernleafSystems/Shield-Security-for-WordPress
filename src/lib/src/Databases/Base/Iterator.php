<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use Elliotchance\Iterator\AbstractPagedIterator;

class Iterator extends AbstractPagedIterator {

	use HandlerConsumer;

	public const PAGE_LIMIT = 50;

	/**
	 * @var Select|mixed
	 */
	private $selector;

	/**
	 * @var int
	 */
	private $totalSize;

	/**
	 * @var array
	 */
	private $customFilters = [];

	/**
	 * @return EntryVO|mixed|null
	 */
	public function current() {
		return parent::current();
	}

	public function getCustomQueryFilters() :array {
		return is_array( $this->customFilters ) ? $this->customFilters : [];
	}

	protected function getDefaultQueryFilters() :array {
		return [
			'orderby' => 'id',
			'order'   => 'ASC',
		];
	}

	protected function getFinalQueryFilters() :array {
		return array_merge( $this->getDefaultQueryFilters(), $this->getCustomQueryFilters() );
	}

	/**
	 * @param int $nPage - always starts at 0
	 * @return array
	 */
	public function getPage( $nPage ) {
		$aParams = $this->getFinalQueryFilters();

		$this->getSelector()
			 ->setResultsAsVo( true )
			 ->setPage( $nPage + 1 ) // Pages start at 1, not zero.
			 ->setLimit( $this->getPageSize() )
			 ->setOrderBy( $aParams[ 'orderby' ], $aParams[ 'order' ] );

		return $this->runQuery();
	}

	/**
	 * @return int
	 */
	public function getPageSize() {
		return static::PAGE_LIMIT;
	}

	/**
	 * @return Select|mixed
	 */
	public function getSelector() {
		if ( empty( $this->selector ) ) {
			$this->selector = $this->getDbHandler()->getQuerySelector();
		}
		return $this->selector;
	}

	/**
	 * @return int
	 */
	public function getTotalSize() {
		return $this->totalSize ?? $this->totalSize = $this->runQueryCount();
	}

	/**
	 * @return EntryVO[]|mixed[]
	 */
	protected function runQuery() {
		return ( clone $this->getSelector() )->query();
	}

	protected function runQueryCount() :int {
		return (int)( clone $this->getSelector() )->count();
	}

	/**
	 * @param Select|mixed $selector
	 * @return $this
	 */
	public function setSelector( $selector ) {
		$this->selector = $selector;
		return $this;
	}
}