<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * How PHP on this server can be told to prepend the early loader stub, chosen from the PHP SAPI.
 * .user.ini files are read only by the CGI/FastCGI SAPIs; php_value in .htaccess is honoured by
 * Apache mod_php and by LiteSpeed's lsphp.
 */
final class Mechanism {

	public const APACHE_MOD_PHP = 'apache_mod_php';
	public const LITESPEED = 'litespeed';
	public const USER_INI = 'user_ini';
	public const MANUAL = 'manual';

	private string $key;

	private bool $userIniAvailable;

	private bool $apacheCompatibleServer;

	private function __construct( string $key, bool $userIniAvailable, bool $apacheCompatibleServer ) {
		$this->key = $key;
		$this->userIniAvailable = $userIniAvailable;
		$this->apacheCompatibleServer = $apacheCompatibleServer;
	}

	public static function fromEnvironment() :self {
		return self::detect(
			\PHP_SAPI,
			(string)\ini_get( 'user_ini.filename' ),
			(string)( $_SERVER[ 'SERVER_SOFTWARE' ] ?? '' )
		);
	}

	public static function detect( string $sapi, string $userIniFilename, string $serverSoftware ) :self {
		$sapi = \strtolower( $sapi );
		$userIniAvailable = self::isUsableUserIniFilename( $userIniFilename );
		$apacheCompatible = \preg_match( '#apache|litespeed#i', $serverSoftware ) === 1;

		if ( $sapi === 'apache2handler' ) {
			$key = self::APACHE_MOD_PHP;
		}
		elseif ( $sapi === 'litespeed' ) {
			$key = self::LITESPEED;
		}
		elseif ( $userIniAvailable && \in_array( $sapi, [ 'fpm-fcgi', 'cgi-fcgi', 'cgi' ], true ) ) {
			$key = self::USER_INI;
		}
		else {
			$key = self::MANUAL;
		}

		return new self( $key, $userIniAvailable, $apacheCompatible || $sapi === 'litespeed' );
	}

	public function key() :string {
		return $this->key;
	}

	public function isAutomatic() :bool {
		return $this->key !== self::MANUAL;
	}

	public function writesUserIni() :bool {
		return $this->key === self::USER_INI || ( $this->key === self::LITESPEED && $this->userIniAvailable );
	}

	public function writesHtaccessPrepend() :bool {
		return \in_array( $this->key, [ self::APACHE_MOD_PHP, self::LITESPEED ], true );
	}

	/**
	 * .htaccess also denies web access to .user.ini wherever .user.ini is written on an Apache-compatible server.
	 */
	public function writesHtaccess() :bool {
		return $this->writesHtaccessPrepend() || ( $this->writesUserIni() && $this->apacheCompatibleServer );
	}

	/**
	 * Longest time PHP may keep using a previous configuration after the files change. .htaccess
	 * is read on every request under mod_php, but LiteSpeed and .user.ini are cached.
	 */
	public function cacheSeconds( int $userIniCacheTtl ) :int {
		return $this->key === self::APACHE_MOD_PHP ? 0 : \max( 300, $userIniCacheTtl );
	}

	public static function isUsableUserIniFilename( string $filename ) :bool {
		return \preg_match( '#^\.?[A-Za-z0-9][A-Za-z0-9._-]*$#', $filename ) === 1;
	}
}
