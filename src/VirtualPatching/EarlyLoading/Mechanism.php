<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * How PHP on this server can be told to prepend the early loader stub, chosen from the PHP SAPI.
 * .user.ini files are read only by the CGI/FastCGI SAPIs; php_value in .htaccess is honoured by
 * Apache mod_php and by LiteSpeed's lsphp.
 */
final readonly class Mechanism {

	private function __construct(
		private MechanismKind $kind,
		private bool $userIniAvailable,
		private bool $apacheCompatibleServer
	) {
	}

	public static function detect( string $sapi, string $userIniFilename, string $serverSoftware ) :self {
		$sapi = \strtolower( $sapi );
		$userIniAvailable = self::isUsableUserIniFilename( $userIniFilename );

		$kind = match ( true ) {
			$sapi === 'apache2handler' => MechanismKind::ApacheModPhp,
			$sapi === 'litespeed' => MechanismKind::Litespeed,
			$userIniAvailable && \in_array( $sapi, [ 'fpm-fcgi', 'cgi-fcgi', 'cgi' ], true ) => MechanismKind::UserIni,
			default => MechanismKind::Manual,
		};

		return new self(
			$kind,
			$userIniAvailable,
			$sapi === 'litespeed' || \preg_match( '#apache|litespeed#i', $serverSoftware ) === 1
		);
	}

	public function kind() :MechanismKind {
		return $this->kind;
	}

	public function writesUserIni() :bool {
		return $this->kind === MechanismKind::UserIni || ( $this->kind === MechanismKind::Litespeed && $this->userIniAvailable );
	}

	/**
	 * .htaccess also denies web access to .user.ini wherever .user.ini is written on an Apache-compatible server.
	 */
	public function writesHtaccess() :bool {
		return $this->kind->setsPrependInHtaccess() || ( $this->writesUserIni() && $this->apacheCompatibleServer );
	}

	public static function isUsableUserIniFilename( string $filename ) :bool {
		return \preg_match( '#^\.?[A-Za-z0-9][A-Za-z0-9._-]*$#', $filename ) === 1;
	}
}
