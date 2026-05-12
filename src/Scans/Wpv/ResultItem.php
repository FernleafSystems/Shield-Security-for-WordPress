<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

/**
 * @property bool $is_vulnerable
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem {

	public function getDescriptionForAudit() :string {
		return sprintf( '%s: %s', $this->VO->item_type === 'p' ? 'Plugin' : 'Theme', $this->VO->item_id );
	}
}
