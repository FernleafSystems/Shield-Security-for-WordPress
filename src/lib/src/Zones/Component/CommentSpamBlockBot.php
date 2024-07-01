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

	public function enabledStatus() :string {
		return self::con()->comps->opts_lookup->enabledAntiBotCommentSpam() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}