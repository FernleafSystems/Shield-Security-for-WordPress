<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options;

class CommentApprovedMinimum extends Base {

	public const SLUG = 'comment_approved_minimum';
	public const WEIGHT = 10;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Comments();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->getApprovedMinimum() > 1;
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Comments();
		return $mod->isModOptEnabled() ? $this->link( 'trusted_commenter_minimum' ) : $this->link( 'enable_comments_filter' );
	}

	public function title() :string {
		return __( 'Minimum Comment Auto-Approval', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Comments are auto-approved only if they have at least 1 other approved comment.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Comments are auto-approved only if they have at least 1 other approved comment.", 'wp-simple-firewall' );
	}
}