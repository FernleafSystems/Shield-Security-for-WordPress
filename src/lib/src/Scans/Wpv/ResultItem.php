<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\VulnVO;

/**
 * Class ResultItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 * @property string $slug
 * @property string $context
 * @property int    $wpvuln_id
 * @property array  $wpvuln_vo
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem {

	public function generateHash() :string {
		return md5( $this->slug.$this->wpvuln_id );
	}

	public function getDescriptionForAudit() :string {
		return sprintf( '%s: %s', $this->context, $this->slug );
	}

	public function getVulnVo() :VulnVO {
		return ( new VulnVO() )->applyFromArray( $this->wpvuln_vo );
	}
}