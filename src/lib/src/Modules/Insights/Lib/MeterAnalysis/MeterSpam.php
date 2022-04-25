<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterSpam extends MeterBase {

	const SLUG = 'spam';

	protected function title() :string {
		return __( 'SPAM Protection', 'wp-simple-firewall' );
	}

	protected function getComponentSlugs() :array {
		return [
			'comment_spam_antibot',
			'comment_spam_human',
			'contact_forms_spam',
			'comment_approved_minimum',
		];
	}
}