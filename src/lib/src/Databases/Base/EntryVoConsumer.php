<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

trait EntryVoConsumer {

	/**
	 * @var EntryVO
	 */
	private $entryVO;

	/**
	 * @return EntryVO|mixed
	 */
	public function getEntryVO() {
		return $this->entryVO;
	}

	/**
	 * @param EntryVO $entry
	 * @return $this
	 */
	public function setEntryVO( $entry ) {
		$this->entryVO = $entry;
		return $this;
	}
}