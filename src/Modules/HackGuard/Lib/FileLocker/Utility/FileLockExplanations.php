<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility;

/**
 * Why each critical file is worth locking, in a few lines shown beside the file.
 */
class FileLockExplanations {

	/**
	 * A lock's file key comes from its stored record, so one that's no longer supported has nothing to say.
	 */
	public function forFileKey( string $fileKey ) :string {
		return [
				   'wpconfig'        => __( "Every wp-config file on a WordPress site is unique, so the only way to detect changes is to create a snapshot of the file. File Locker will instantly detect changes to your wp-config file. Some of these will be completely legitimate, but it's always worth reviewing them when you receive an alert that your wp-config file has changed, as this is a common file for the injection of malware.", 'wp-simple-firewall' ),
				   'theme_functions' => __( "Your primary theme's functions.php is potentially a source of unique theme customizations, and so it is easy for malware to hide in there without being noticed. File Locker will instantly detect changes to your functions.php file and alert you.", 'wp-simple-firewall' ),
				   'root_htaccess'   => __( "The .htaccess file at the root of your WordPress site tells your web server how to handle every request, which makes it a common place for attackers to add malicious redirects and rules. File Locker will instantly detect changes to the file and alert you. Some of these will be completely legitimate, as WordPress and some plugins write to this file too.", 'wp-simple-firewall' ),
				   'root_index'      => __( 'The index.php at the root of your WordPress site is a common target for malware injection. File Locker will instantly detect changes to the file and alert you.', 'wp-simple-firewall' ),
				   'root_webconfig'  => __( 'The Web.config file at the root of your WordPress site tells your IIS web server how to handle every request, which makes it a common place for attackers to add malicious redirects and rules. File Locker will instantly detect changes to the file and alert you.', 'wp-simple-firewall' ),
			   ][ $fileKey ] ?? '';
	}
}
