<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Trait EntryVoConsumer
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Base
 */
trait EntryVoConsumer {

	/**
	 * @var EntryVO
	 */
	private $oEntryVO;

	/**
	 * @return EntryVO|mixed
	 */
	public function getEntryVO() {
		return $this->oEntryVO;
	}

	/**
	 * @param EntryVO $entry
	 * @return $this
	 */
	public function setEntryVO( $entry ) {
		$this->oEntryVO = $entry;
		return $this;
	}
}