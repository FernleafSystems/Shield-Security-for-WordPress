<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class CommentSpamBlockBot extends Base {

	public function title() :string {
		return __( 'Block Bot Comment SPAM', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Block the most common form of WordPress Comment SPAM.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Edit bot-SPAM comment settings', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->comps->opts_lookup->enabledAntiBotCommentSpam() ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "silentCAPTCHA isn't protecting your WP Comments against bot SPAM.", 'wp-simple-firewall' );
		}

		return $status;
	}
}