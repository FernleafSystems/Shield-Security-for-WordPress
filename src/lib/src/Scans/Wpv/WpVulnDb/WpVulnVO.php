<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb;

/**
 * Class WpVulnVO
 * @property int    $id
 * @property string $title
 * @property string $vuln_type
 * @property string $fixed_in
 * @property string $references
 * @property int    $updated_at
 * @property int    $created_at
 * @property int    $published_date
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb
 */
class WpVulnVO {

	use \FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

	/**
	 * @return string
	 */
	public function getUrl() {
		return sprintf( 'https://wpvulndb.com/vulnerabilities/%s', $this->id );
	}
}