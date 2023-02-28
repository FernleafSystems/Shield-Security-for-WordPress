<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	Scans as ScansDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon,
	ModConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * @property string[] $wheres
 */
abstract class RetrieveBase extends DynPropertiesClass {

	use ModConsumer;
	use ScanControllerConsumer;

	public const MOD = ModCon::SLUG;
	public const ABBR_RESULTITEMMETA = '`rim`';

	protected $additionalWheres = [];

	protected $latestScanID;

	abstract public function buildQuery( array $selectFields = [] ) :string;

	protected function getLatestScanID() :int {
		if ( !isset( $this->latestScanID ) ) {
			/** @var ScansDB\Ops\Select $scansSelector */
			$scansSelector = $this->mod()->getDbH_Scans()->getQuerySelector();
			$latest = $scansSelector->getLatestForScan( $this->getScanController()->getSlug() );
			$this->latestScanID = empty( $latest ) ? -1 : $latest->id;
		}
		return $this->latestScanID;
	}

	abstract protected function getBaseQuery( bool $joinWithResultMeta = false ) :string;

	public function getAdditionalWheres() :array {
		return is_array( $this->additionalWheres ) ? $this->additionalWheres : [];
	}

	public function getWheres() :array {
		return array_filter( array_map( 'trim', is_array( $this->wheres ) ? $this->wheres : [] ) );
	}

	/**
	 * @return $this
	 */
	public function addWheres( array $wheres, bool $merge = true ) {
		$this->wheres = $merge ? array_merge( $this->getWheres(), $wheres ) : $wheres;
		return $this;
	}
}