<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

/**
 * @deprecated 19.1
 */
class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function getApprovedMinimum() :int {
		return (int)$this->getOpt( 'trusted_commenter_minimum', 1 );
	}

	/**
	 * @return string[]
	 * @deprecated 19.1
	 */
	public function getTrustedRoles() :array {
		$roles = [];
		if ( self::con()->isPremiumActive() ) {
			$roles = $this->getOpt( 'trusted_user_roles', [] );
		}
		return \is_array( $roles ) ? $roles : [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledAntiBot() :bool {
		return $this->isOpt( 'enable_antibot_comments', 'Y' );
	}
}