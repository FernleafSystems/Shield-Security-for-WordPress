<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use Elliotchance\Iterator\AbstractPagedIterator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

class IpRulesIterator extends AbstractPagedIterator {

	use ModConsumer;

	/**
	 * @var LoadIpRules
	 */
	protected $loader;

	protected $pageSize = 1000;

	protected $useCache = false;

	/**
	 * @return IpRuleRecord
	 */
	public function current() {
		return parent::current();
	}

	/**
	 * @var int
	 */
	protected $total;

	public function getPageSize() {
		return $this->pageSize;
	}

	public function getTotalSize() {
		return $this->total ?? $this->total = $this->getLoader()->countAll();
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function getPage( $pageNumber ) {
		$loader = $this->getLoader();
		$loader->limit = $this->getPageSize();
		$loader->offset = $pageNumber*$this->getPageSize();
		return array_values( $this->getLoader()->select() );
	}

	public function getLoader() :LoadIpRules {
		if ( !$this->loader instanceof LoadIpRules ) {
			$this->loader = new LoadIpRules();
		}
		return $this->loader;
	}
}