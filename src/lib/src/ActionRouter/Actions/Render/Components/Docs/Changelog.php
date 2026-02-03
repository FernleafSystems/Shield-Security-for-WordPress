<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Docs;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc\ShieldChangelogRetrieve;

class Changelog extends Actions\Render\BaseRender {

	use Actions\Traits\SecurityAdminNotRequired;

	public const SLUG = 'render_changelog';
	public const TEMPLATE = '/wpadmin/components/changelog.twig';

	protected function getRenderData() :array {
		try {
			$changelog = ( new ShieldChangelogRetrieve() )->fromRepo();
		}
		catch ( \Exception $e ) {
			$changelog = ( new ShieldChangelogRetrieve() )->fromFile();
		}
		return [
			'changelog' => $changelog,
			'strings'   => [
				// the keys here must match the changelog item types
				'version'                   => CommonDisplayStrings::get( 'version_label' ),
				'release_date'              => __( 'Release Date', 'wp-simple-firewall' ),
				'pro_only'                  => __( 'Pro Only', 'wp-simple-firewall' ),
				'full_release'              => __( 'Full Release Announcement', 'wp-simple-firewall' ),
				'upgrade_notice_sentence_1' => __( 'Major releases may contain new options, or change existing options.', 'wp-simple-firewall' ),
				'upgrade_notice_sentence_2' => __( 'Please review the', 'wp-simple-firewall' ),
			],
			'vars'      => [
				// the keys here must match the changelog item types
				'badge_types' => [
					'new'      => 'primary',
					'added'    => 'light',
					'improved' => 'info',
					'changed'  => 'warning',
					'fixed'    => 'danger',
					'removed'  => 'danger',
				]
			],
		];
	}
}
