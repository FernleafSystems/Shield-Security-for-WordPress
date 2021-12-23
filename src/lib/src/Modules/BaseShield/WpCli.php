<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class WpCli extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli {

	public function getCfg() :array {
		$cfg = parent::getCfg();
		$cfg[ 'cmd_root' ] = 'shield';
		return $cfg;
	}
}