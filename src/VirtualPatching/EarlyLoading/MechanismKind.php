<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Where PHP is told to prepend the stub. The value is stored with the state.
 */
enum MechanismKind :string {

	/**
	 * php_value in .htaccess, read on every request.
	 */
	case ApacheModPhp = 'apache_mod_php';

	/**
	 * php_value in .htaccess, plus .user.ini when PHP reads it.
	 */
	case Litespeed = 'litespeed';

	/**
	 * .user.ini, read by the CGI/FastCGI SAPIs.
	 */
	case UserIni = 'user_ini';

	/**
	 * The administrator sets auto_prepend_file.
	 */
	case Manual = 'manual';

	public function isAutomatic() :bool {
		return $this !== self::Manual;
	}

	public function setsPrependInHtaccess() :bool {
		return $this === self::ApacheModPhp || $this === self::Litespeed;
	}

	/**
	 * Longest time PHP may keep using a previous configuration after the files change. .htaccess is read on
	 * every request under mod_php, but LiteSpeed and .user.ini are cached, and a manual change is unknown.
	 */
	public function cacheSeconds( int $userIniCacheTtl ) :int {
		return match ( $this ) {
			self::ApacheModPhp => 0,
			self::Litespeed, self::UserIni, self::Manual => \max( 300, $userIniCacheTtl ),
		};
	}
}
