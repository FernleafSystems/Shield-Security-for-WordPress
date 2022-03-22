<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	public function getApprovedMinimum() :int {
		return (int)$this->getOpt( 'trusted_commenter_minimum', 1 );
	}

	public function getHumanSpamFilterItems() :array {
		$aDefault = $this->getOptDefault( 'human_spam_items' );
		$aItems = apply_filters(
			$this->getCon()->prefix( 'human_spam_items' ),
			$this->getOpt( 'human_spam_items', [] )
		);
		return is_array( $aItems ) ? array_intersect( $aDefault, $aItems ) : $aDefault;
	}

	public function getTokenCooldown() :int {
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

	public function getTokenExpireInterval() :int {
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

	public function isEnabledGaspCheck() :bool {
		return $this->isOpt( 'enable_comments_gasp_protection', 'Y' )
			   && ( $this->getTokenExpireInterval() > $this->getTokenCooldown() )
			   && !$this->isEnabledAntiBot();
	}

	public function isEnabledAntiBot() :bool {
		return $this->isOpt( 'enable_antibot_comments', 'Y' );
	}

	public function isEnabledCaptcha() :bool {
		return !$this->isOpt( 'google_recaptcha_style_comments', 'disabled' ) && !$this->isEnabledAntiBot();
	}

	public function isEnabledHumanCheck() :bool {
		return $this->isOpt( 'enable_comments_human_spam_filter', 'Y' )
			   && count( $this->getHumanSpamFilterItems() ) > 0;
	}

	public function setEnabledAntiBot( bool $enabled = true ) {
		$this->setOpt( 'enable_antibot_comments', $enabled ? 'Y' : 'N' );
	}
}