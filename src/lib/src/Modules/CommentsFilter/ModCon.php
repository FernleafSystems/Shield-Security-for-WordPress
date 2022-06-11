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
}