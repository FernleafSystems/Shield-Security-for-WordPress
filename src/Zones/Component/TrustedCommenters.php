<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class TrustedCommenters extends Base {

	public function title() :string {
		return __( 'Trusted Commenters', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Require a commenter to earn trust before comments are auto-approved.', 'wp-simple-firewall' );
	}

	protected function postureWeight() :int {
		return 1;
	}

	protected function status() :array {
		$status = parent::status();
		$minimum = self::con()->comps->opts_lookup->getCommenterTrustedMinimum();
		if ( $minimum > 1 ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
			$status[ 'exp' ][] = __( 'Comments are auto-approved only after a commenter has at least one approved comment.', 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( 'New commenters can be auto-approved before they have an established history.', 'wp-simple-firewall' );
		}
		return $status;
	}
}
