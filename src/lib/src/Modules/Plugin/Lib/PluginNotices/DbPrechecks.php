<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

class DbPrechecks extends Base {

	public function check() :?array {
		$dbPreChecks = self::con()->prechecks[ 'dbs' ];
		return \count( $dbPreChecks ) !== \count( \array_filter( $dbPreChecks ) ) ?
			[
				'id'        => 'db_prechecks_fail',
				'type'      => 'danger',
				'text'      => [
					sprintf(
						'%s %s',
						__( "The Shield database needs to be repaired as certain features won't be available without a valid database.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" data-notice_action="auto_db_repair" class="shield_admin_notice_action text-white">%s</a>',
							'#',
							__( 'Run Database Repair', 'wp-simple-firewall' )
						)
					)
				],
				'locations' => [
					'shield_admin_top_page',
				]
			]
			: null;
	}
}