<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use ZxcvbnPhp\Zxcvbn;

/**
 * Rating the database password loads Zxcvbn's word lists, which is slow, and the password only changes when
 * wp-config.php does. So the rating is cached, and worked out again only when the password changes.
 */
class DbPasswordStrength {

	use PluginControllerConsumer;

	public function isStrong() :bool {
		$password = (string)\DB_PASSWORD;
		return (bool)self::con()->comps->site_query->cache()->remember(
			'wp_db_password_is_strong',
			$password,
			fn() :bool => ( ( new Zxcvbn() )->passwordStrength( $password )[ 'score' ] ?? 0 ) >= 4
		);
	}
}
