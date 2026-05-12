<?php declare( strict_types=1 );

$options = \getopt( '', [
	'plugin-root:',
	'scenario::',
	'class-name::',
	'method::',
] );

$pluginRoot = \is_string( $options[ 'plugin-root' ] ?? null ) ? \trim( (string)$options[ 'plugin-root' ] ) : '';
$scenario = \is_string( $options[ 'scenario' ] ?? null ) ? \trim( (string)$options[ 'scenario' ] ) : 'load';
$className = \is_string( $options[ 'class-name' ] ?? null ) ? \trim( (string)$options[ 'class-name' ] ) : '';
$method = \is_string( $options[ 'method' ] ?? null ) ? \trim( (string)$options[ 'method' ] ) : 'describe';

$result = [
	'ok'         => true,
	'scenario'   => $scenario,
	'pluginRoot' => $pluginRoot,
	'legacyRoot' => '',
	'checks'     => [],
	'errors'     => [],
];

if ( $pluginRoot === '' || $className === '' ) {
	$result[ 'ok' ] = false;
	$result[ 'errors' ][] = 'Missing required --plugin-root or --class-name option.';
	echo \json_encode( $result, \JSON_UNESCAPED_SLASHES ).\PHP_EOL;
	exit( 0 );
}

$normalisePath = static function ( string $path ) :string {
	return \str_replace( '\\', '/', $path );
};

$legacyRoot = \rtrim( $normalisePath( $pluginRoot ), '/' ).'/src/lib/src';
$result[ 'legacyRoot' ] = $legacyRoot;

$shieldPrefix = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\';
\spl_autoload_register(
	static function ( string $autoloadClassName ) use ( $shieldPrefix, $legacyRoot ) :void {
		if ( \strpos( $autoloadClassName, $shieldPrefix ) !== 0 ) {
			return;
		}

		$relative = \substr( $autoloadClassName, \strlen( $shieldPrefix ) );
		$path = $legacyRoot.'/'.\str_replace( '\\', '/', $relative ).'.php';
		if ( \is_file( $path ) ) {
			require_once $path;
		}
	},
	true,
	true
);

try {
	if ( $scenario === 'precheck' ) {
		$result[ 'checks' ][ 'class_found' ] = [
			'ok'    => true,
			'found' => \class_exists( $className, true ),
		];
	}
	else {
		if ( !\class_exists( $className, true ) ) {
			throw new \RuntimeException( 'Legacy autoloader did not resolve the requested class.' );
		}

		$reflection = new \ReflectionClass( $className );
		$filePath = $normalisePath( (string)$reflection->getFileName() );
		if ( \strpos( $filePath, '/src/lib/src/' ) === false ) {
			throw new \RuntimeException( 'Class did not load from legacy path: '.$filePath );
		}

		$instance = new $className();
		if ( !\method_exists( $instance, $method ) ) {
			throw new \RuntimeException( 'Requested method missing on loaded class: '.$method );
		}

		$result[ 'checks' ][ 'class_loaded' ] = [
			'ok'      => true,
			'details' => [
				'file'  => $filePath,
				'value' => $instance->{$method}(),
			],
		];
	}
}
catch ( \Throwable $e ) {
	$result[ 'ok' ] = false;
	$result[ 'errors' ][] = $e->getMessage();
}

echo \json_encode( $result, \JSON_UNESCAPED_SLASHES ).\PHP_EOL;
exit( 0 );
