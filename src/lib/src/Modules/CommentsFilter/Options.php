<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	public function preSave() :void {
		if ( $this->isOptChanged( 'trusted_user_roles' ) ) {
			$this->setOpt( 'trusted_user_roles',
				\array_unique( \array_filter( \array_map(
					function ( $role ) {
						return sanitize_key( \strtolower( $role ) );
					},
					$this->getTrustedRoles()
				) ) )
			);
		}
	}

	public function getApprovedMinimum() :int {
		return (int)$this->getOpt( 'trusted_commenter_minimum', 1 );
	}

	public function getHumanSpamFilterItems() :array {
		$default = $this->getOptDefault( 'human_spam_items' );
		$items = apply_filters( self::con()->prefix( 'human_spam_items' ), $this->getOpt( 'human_spam_items', [] ) );
		return \is_array( $items ) ? \array_intersect( $default, $items ) : $default;
	}

	/**
	 * @return string[]
	 */
	public function getTrustedRoles() :array {
		$roles = [];
		if ( self::con()->isPremiumActive() ) {
			$roles = $this->getOpt( 'trusted_user_roles', [] );
		}
		return \is_array( $roles ) ? $roles : [];
	}

	public function isEnabledAntiBot() :bool {
		return $this->isOpt( 'enable_antibot_comments', 'Y' );
	}

	public function isEnabledHumanCheck() :bool {
		return $this->isOpt( 'enable_comments_human_spam_filter', 'Y' )
			   && \count( $this->getHumanSpamFilterItems() ) > 0;
	}

	public function setEnabledAntiBot( bool $enabled = true ) {
		$this->setOpt( 'enable_antibot_comments', $enabled ? 'Y' : 'N' );
	}
}