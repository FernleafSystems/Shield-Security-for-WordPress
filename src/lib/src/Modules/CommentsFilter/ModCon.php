<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		// clean roles
		$opts->setOpt( 'trusted_user_roles',
			array_unique( array_filter( array_map(
				function ( $role ) {
					return sanitize_key( strtolower( $role ) );
				},
				$opts->getTrustedRoles()
			) ) )
		);
	}

	public function getSpamBlacklistFile() :string {
		return $this->getCon()->paths->forCacheItem( 'spamblacklist.txt' );
	}

	/**
	 * @deprecated 15.0
	 */
	public function isEnabledCaptcha() :bool {
		return false;
	}

	/**
	 * @deprecated 15.0
	 */
	public function ensureCorrectCaptchaConfig() {
	}

	/**
	 * @deprecated 15.0
	 */
	public function getCaptchaCfg() :\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO {
		return parent::getCaptchaCfg();
	}
}