<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

/**
 * @property string $slug
 * @property string $context
 * @property int    $last_updated_at
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem {

	public function getDescriptionForAudit() :string {
		return sprintf( '%s: %s', $this->context, $this->slug );
	}
}