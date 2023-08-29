<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options;

class CommentApprovedMinimum extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'comment_approved_minimum';
	public const WEIGHT = 1;

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_Comments();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->getApprovedMinimum() > 1;
	}

	protected function getOptConfigKey() :string {
		return 'trusted_commenter_minimum';
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