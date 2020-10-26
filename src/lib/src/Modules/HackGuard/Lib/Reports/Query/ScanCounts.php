<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports\Query;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ScanCounts {

	use ModConsumer;

	/**
	 * @var int
	 */
	public $from;

	/**
	 * @var int
	 */
	public $to;

	/**
	 * @var bool
	 */
	public $ignored = false;

	/**
	 * @var bool
	 */
	public $notified = false;

	public function __construct( $from = null, $to = null ) {
		$this->from = is_int( $from ) ? $from : 0;
		$this->to = is_int( $to ) ? $to : Services::Request()->ts();
	}

	/**
	 * @return int[] - key is scan slug
	 */
	public function all() :array {
		return array_merge(
			$this->standard(),
			$this->filelocker()
		);
	}

	/**
	 * @return int[]
	 */
	public function filelocker() :array {
		return [
			'filelocker' => count( ( new HackGuard\Lib\FileLocker\Ops\LoadFileLocks() )
				->setMod( $this->getMod() )
				->withProblemsNotNotified() )
		];
	}

	/**
	 * @return int[] - key is scan slug
	 */
	public function standard() :array {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		/** @var Scanner\Select $qSel */
		$qSel = $mod->getDbHandler_ScanResults()->getQuerySelector();

		$counts = [];

		foreach ( $opts->getScanSlugs() as $slug ) {
			$qSel->filterByScan( $slug )
				 ->filterByCreatedAt( $this->from, '>=' )
				 ->filterByCreatedAt( $this->to, '<=' );
			if ( isset( $this->ignored ) ) {
				$this->ignored ? $qSel->filterByIgnored() : $qSel->filterByNotIgnored();
			}
			if ( isset( $this->notified ) ) {
				$this->notified ? $qSel->filterByNotified() : $qSel->filterByNotNotified();
			}
			$counts[ $slug ] = $qSel->count();
		}

		return $counts;
	}
}
