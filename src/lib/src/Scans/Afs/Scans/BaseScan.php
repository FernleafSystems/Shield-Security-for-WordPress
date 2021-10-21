<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;

abstract class BaseScan {

	use ScanActionConsumer;

	/**
	 * @var string
	 */
	protected $pathFragment;

	/**
	 * @var string
	 */
	protected $pathFull;

	public function __construct( string $pathFull ) {
		$this->setPathFull( $pathFull );
	}

	abstract public function scan() :bool;

	public function setPathFull( string $pathFull ) {
		$this->pathFull = $pathFull;
		$this->pathFragment = str_replace( wp_normalize_path( ABSPATH ), '', $pathFull );
	}
}