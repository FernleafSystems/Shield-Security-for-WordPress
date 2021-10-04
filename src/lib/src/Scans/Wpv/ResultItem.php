<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\VulnVO;

/**
 * @property string $slug
 * @property int    $wpvuln_id
 * @property array  $wpvuln_vo
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem {

	public function generateHash() :string {
		return md5( $this->slug.$this->wpvuln_id );
	}

	public function getDescriptionForAudit() :string {
		return sprintf( '%s: %s', ( strpos( $this->slug, '/' ) ? 'Plugin' : 'Theme' ), $this->slug );
	}

	public function getVulnVo() :VulnVO {
		return ( new VulnVO() )->applyFromArray( $this->wpvuln_vo );
	}
}