<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	/**
	 * 'enable_antibot_check' option key was used in both Comments and Login Guard.
	 * So we needed to move 1 of them to a new, unique, option key.
	 *
	 * In this upgrade we switch over the spam bot detection to now use ADE
	 */
	protected function upgrade_1411() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isOpt( 'enable_antibot_check', 'Y' ) || $opts->isEnabledCaptcha() || $opts->isEnabledGaspCheck() ) {
			$opts->setEnabledAntiBot();
		}
	}
}