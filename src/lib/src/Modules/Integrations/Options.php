<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function isEnabledMainWP() :bool {
		return $this->isOpt( 'enable_mainwp', 'Y' );
	}

	public function isEnabledSpamDetect() :bool {
		return ( $this->isOpt( 'enable_spam_antibot', 'Y' ) || $this->isOpt( 'enable_spam_human', 'Y' ) )
			   && !empty( $this->getOpt( 'form_spam_providers' ) );
	}
}