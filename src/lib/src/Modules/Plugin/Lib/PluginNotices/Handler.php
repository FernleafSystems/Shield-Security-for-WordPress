<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

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
				SiteInLockdown::class,
				GloballyDisabled::class,
				HighReputation::class,
				IpStatus::class,
				LicenseStatus::class,
				RulesEngine::class,
				SelfVersion::class,
				GoPro::class,
			]
		) );

		$normalised = [];
		foreach ( $issues as $issue ) {
			if ( empty( $issue[ 'id' ] ) ) {
				error_log( sprintf( 'Invalid issue defined without ID: %s', var_export( $issue, true ) ) );
			}
			elseif ( isset( $normalised[ $issue[ 'id' ] ] ) ) {
				error_log( sprintf( 'Duplicate issue ID: %s', var_export( $issue, true ) ) );
			}
			else {
				$normalised[ $issue[ 'id' ] ] = \array_merge( [
					'type'      => 'warning',
					'text'      => 'no text provided',
					'locations' => [],
					'flags'     => [],
				], $issue );
			}
		}
		return $normalised;
	}
}