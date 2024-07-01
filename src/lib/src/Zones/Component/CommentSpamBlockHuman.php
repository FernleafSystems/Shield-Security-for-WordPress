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

	public function enabledStatus() :string {
		return self::con()->comps->opts_lookup->enabledHumanCommentSpam() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}