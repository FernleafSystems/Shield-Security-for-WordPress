<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1900() {
		$modComms = self::con()->modules[ 'comms' ] ?? null;
		if ( !empty( $modComms ) ) {
			$this->opts()->setOpt( 'suresend_emails', $modComms->opts()->getOpt( 'suresend_emails' ) );
		}
	}
}