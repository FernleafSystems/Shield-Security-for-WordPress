<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\TranslationsForceDownload;
use FernleafSystems\Wordpress\Services\Services;

class TranslationsQueued extends Base {

	public function check() :?array {
		$con = self::con();
		if ( !$con->comps->translation_downloads->isQueueRelevantToLocale(
			\function_exists( 'determine_locale' ) ? determine_locale() : get_locale()
		) ) {
			return null;
		}

		$queue = $con->comps->translation_downloads->getQueue();
		$count = \count( $queue );

		$translationsQueued = \sprintf(
			_n(
				'%d translation is queued for download',
				'%d translations are queued for download',
				$count,
				'wp-simple-firewall'
			),
			$count
		);

		$nextScheduled = wp_next_scheduled( $con->prefix( 'adhoc_locales_download' ) );
		if ( !empty( $nextScheduled ) ) {
			$minsRemaining = \max( 1, (int)\ceil( ( $nextScheduled - Services::Request()->ts() )/60 ) );
			$message = $translationsQueued.' '.\sprintf(
					_n(
						'and will be processed automatically in approximately %d minute.',
						'and will be processed automatically in approximately %d minutes.',
						$minsRemaining,
						'wp-simple-firewall'
					),
					$minsRemaining
				);
		}
		else {
			$message = $translationsQueued.' '.__( 'and will be processed shortly.', 'wp-simple-firewall' );
		}

		return [
			'id'        => 'translations_queued',
			'type'      => 'info',
			'text'      => [
				sprintf(
					'%s %s',
					sprintf( '%s: %s - ', $con->labels->Name, __( 'Translations Queued', 'wp-simple-firewall' ) ),
					sprintf(
						'%s <a href="%s" data-notice_action="%s" class="shield_admin_notice_action">%s</a>',
						$message,
						'#',
						TranslationsForceDownload::SLUG,
						__( 'Download Now', 'wp-simple-firewall' )
					)
				)
			],
			'locations' => [
				'shield_admin_top_page',
			]
		];
	}
}
