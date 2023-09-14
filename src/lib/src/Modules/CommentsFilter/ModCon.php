<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'comments_filter';

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->opts();

		// clean roles
		$opts->setOpt( 'trusted_user_roles',
			\array_unique( \array_filter( \array_map(
				function ( $role ) {
					return sanitize_key( \strtolower( $role ) );
				},
				$opts->getTrustedRoles()
			) ) )
		);
	}
}