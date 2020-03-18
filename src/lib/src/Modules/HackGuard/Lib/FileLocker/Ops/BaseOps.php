<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BaseOps {

	use ModConsumer;
	use HandlerConsumer;

	/**
	 * @var Databases\FileLocker\EntryVO[]
	 */
	private static $AllFileRecords;

	/**
	 * @var FileLocker\File
	 */
	protected $oFile;

	public function __construct( FileLocker\File $oFile ) {
		$this->oFile = $oFile;
	}

	/**
	 * @return Databases\FileLocker\EntryVO[]|null
	 */
	protected function getFileRecords() {
		if ( is_null( self::$AllFileRecords ) ) {
			/** @var Databases\FileLocker\Handler $oDbH */
			$oDbH = $this->getDbHandler();
			self::$AllFileRecords = $oDbH->getQuerySelector()->all();
		}
		return self::$AllFileRecords;
	}

}