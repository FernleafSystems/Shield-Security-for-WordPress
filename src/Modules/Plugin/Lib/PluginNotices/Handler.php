<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ErrorLog;

class Handler {

	public function build() :array {
		$issues = \array_filter( \array_map(
			function ( string $class ) {
				/** @var Base|string $class */
				return ( new $class() )->check();
			},
			[
				ConflictAkismet::class,
				ConflictMonolog::class,
				DbPrechecks::class,
				ForceOff::class,
				TranslationsQueued::class,
				LegacyDashboardNotices::class,
				SiteInLockdown::class,
				GloballyDisabled::class,
				HighReputation::class,
				ImportExportNetworkInvite::class,
				IpStatus::class,
				LicenseStatus::class,
				RulesEngine::class,
				PhpFutureMinimum::class,
				SelfVersion::class,
				GoPro::class,
			]
		) );

		$normalised = [];
		foreach ( $issues as $issue ) {
			if ( empty( $issue[ 'id' ] ) ) {
				ErrorLog::write( sprintf( 'Invalid issue defined without ID: %s', ErrorLog::describe( $issue ) ) );
			}
			elseif ( isset( $normalised[ $issue[ 'id' ] ] ) ) {
				ErrorLog::write( sprintf( 'Duplicate issue ID: %s', ErrorLog::describe( $issue ) ) );
			}
			else {
				$normalised[ $issue[ 'id' ] ] = \array_merge( [
					'type'        => 'warning',
					'text'        => [ 'no text provided' ],
					'locations'   => [],
					'flags'       => [],
					'can_dismiss' => false,
				], $issue );
			}
		}
		return $normalised;
	}
}
