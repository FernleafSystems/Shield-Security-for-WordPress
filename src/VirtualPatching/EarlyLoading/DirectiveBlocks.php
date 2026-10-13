<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Builds the bodies of Shield's marked blocks in .htaccess and .user.ini.
 */
final class DirectiveBlocks {

	/**
	 * @throws \InvalidArgumentException
	 */
	public static function htaccess( string $stubPath, Mechanism $mechanism, string $userIniFilename ) :string {
		$quoted = self::quoted( $stubPath );

		$modules = match ( $mechanism->kind() ) {
			MechanismKind::ApacheModPhp => [ 'mod_php5.c', 'mod_php7.c', 'mod_php.c' ],
			MechanismKind::Litespeed => [ 'LiteSpeed', 'lsapi_module' ],
			MechanismKind::UserIni, MechanismKind::Manual => [],
		};

		$lines = [];
		foreach ( $modules as $module ) {
			$lines[] = sprintf( '<IfModule %s>', $module );
			$lines[] = "\tphp_value auto_prepend_file ".$quoted;
			$lines[] = '</IfModule>';
		}

		if ( $mechanism->writesUserIni() ) {
			if ( !Mechanism::isUsableUserIniFilename( $userIniFilename ) ) {
				throw new \InvalidArgumentException( 'Unusable user_ini.filename.' );
			}
			\array_push( $lines,
				sprintf( '<Files "%s">', $userIniFilename ),
				'<IfModule mod_authz_core.c>',
				"\tRequire all denied",
				'</IfModule>',
				'<IfModule !mod_authz_core.c>',
				"\tOrder deny,allow",
				"\tDeny from all",
				'</IfModule>',
				'</Files>'
			);
		}

		return \implode( "\n", $lines );
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public static function userIni( string $stubPath ) :string {
		return 'auto_prepend_file = '.self::quoted( $stubPath );
	}

	/**
	 * Single-quoted values are literal in both Apache configuration and INI files, so a path
	 * that could break out of the quotes or the line is refused rather than escaped.
	 * @throws \InvalidArgumentException
	 */
	public static function quoted( string $path ) :string {
		if ( !self::isSafePath( $path ) ) {
			throw new \InvalidArgumentException( 'The path cannot be written safely to a server configuration file.' );
		}
		return "'".self::normalise( $path )."'";
	}

	public static function isSafePath( string $path ) :bool {
		$path = self::normalise( $path );
		return $path !== ''
			   && \preg_match( "#['\"\\r\\n\\x00]#", $path ) !== 1
			   && \preg_match( '#^(/|[A-Za-z]:/)#', $path ) === 1;
	}

	public static function normalise( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}
