<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class CommentSpamBlockHuman extends Base {

	public function title() :string {
		return __( 'Block Human Comment SPAM', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Block human Comment SPAM with dictionary-based scanning that preserves your privacy.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Edit human-SPAM comment settings', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->comps->opts_lookup->enabledHumanCommentSpam() ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "There is no protection against WP Comment SPAM posted by humans.", 'wp-simple-firewall' );
		}

		return $status;
	}
}