<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	/**
	 * @return int
	 */
	public function getApprovedMinimum() {
		return (int)$this->getOpt( 'trusted_commenter_minimum', 1 );
	}

	/**
	 * @return string[]
	 */
	public function getHumanSpamFilterItems() {
		$aDefault = $this->getOptDefault( 'human_spam_items' );
		$aItems = apply_filters(
			$this->getCon()->prefix( 'human_spam_items' ),
			$this->getOpt( 'human_spam_items', [] )
		);
		return is_array( $aItems ) ? array_intersect( $aDefault, $aItems ) : $aDefault;
	}

	/**
	 * @return int
	 */
	public function getTokenCooldown() {
		if ( (int)$this->getOpt( 'comments_cooldown', 10 ) < 1 ) {
			$this->resetOptToDefault( 'comments_cooldown' );
		}
		return (int)max( 0,
			apply_filters(
				$this->getCon()->prefix( 'comments_cooldown' ),
				$this->getOpt( 'comments_cooldown', 10 )
			)
		);
	}

	/**
	 * @return int
	 */
	public function getTokenExpireInterval() {
		return (int)max( 0,
			apply_filters(
				$this->getCon()->prefix( 'comments_expire' ),
				$this->getDef( 'comments_expire' )
			)
		);
	}

	/**
	 * @return string[]
	 */
	public function getTrustedRoles() {
		$aRoles = [];
		if ( $this->isPremium() ) {
			$aRoles = $this->getOpt( 'trusted_user_roles', [] );
		}
		return is_array( $aRoles ) ? $aRoles : [];
	}

	/**
	 * @return bool
	 */
	public function isEnabledGaspCheck() {
		return $this->isOpt( 'enable_comments_gasp_protection', 'Y' )
			   && ( $this->getTokenExpireInterval() > $this->getTokenCooldown() );
	}

	/**
	 * @return bool
	 */
	public function isEnabledCaptcha() {
		return !$this->isOpt( 'google_recaptcha_style_comments', 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isEnabledHumanCheck() {
		return $this->isOpt( 'enable_comments_human_spam_filter', 'Y' )
			   && count( $this->getHumanSpamFilterItems() ) > 0;
	}
}