<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\DbPasswordStrength;

class WpDbPassword extends Base {

	public const SLUG = 'wp_db_password';
	public const WEIGHT = 3;

	protected function testIfProtected() :bool {
		return ( new DbPasswordStrength() )->isStrong();
	}

	public function title() :string {
		return __( 'MySQL DB Password', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "WP Database password is very strong.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "WP Database password appears to be weak.", 'wp-simple-firewall' );
	}

	public function guidance() :array {
		return [
			'checks'   => __( "Shield rates the strength of the password WordPress uses to connect to its database. This is the DB_PASSWORD value in your site's wp-config.php file. It isn't the password of any WordPress user.", 'wp-simple-firewall' ),
			'why'      => [
				__( "The database holds everything on your site: content, user accounts, orders and settings. If the database can be reached from outside your server, or another site on the same server is broken into, a weak password is easy to guess.", 'wp-simple-firewall' ),
			],
			'steps'    => [
				__( 'Take a backup of your site, including the wp-config.php file.', 'wp-simple-firewall' ),
				__( "In your hosting control panel, open the databases section and find the database user this site uses. Its name is the DB_USER value in wp-config.php.", 'wp-simple-firewall' ),
				__( "Check whether anything else connects as that user, such as another site. If so, it stops working until it's given the new password too, so have your host change them together.", 'wp-simple-firewall' ),
				__( 'Set a new, long, random password for that database user. Use a password generator and aim for at least 20 characters.', 'wp-simple-firewall' ),
				__( "Straight away, put the same password into wp-config.php as the DB_PASSWORD value and save the file. Until the two match, your site shows a database connection error.", 'wp-simple-firewall' ),
				__( "Load your site to confirm it's working.", 'wp-simple-firewall' ),
			],
			'who'      => __( "Your web host or developer normally does this. If you're not comfortable editing wp-config.php, send them these steps. Some hosts set this password themselves and don't let you change it: if yours does, ignore this item.", 'wp-simple-firewall' ),
			'practice' => [
				__( "Don't use the database password for anything else.", 'wp-simple-firewall' ),
				__( 'Give each site its own database user, not one shared between sites.', 'wp-simple-firewall' ),
			],
		];
	}
}
