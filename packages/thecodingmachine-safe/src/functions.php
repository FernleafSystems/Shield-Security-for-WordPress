<?php declare( strict_types=1 );

namespace Safe;

use Safe\Exceptions\ArrayException;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;
use Safe\Exceptions\MiscException;
use Safe\Exceptions\OpensslException;
use Safe\Exceptions\SplException;
use Safe\Exceptions\StringsException;
use Safe\Exceptions\UrlException;

if ( !\function_exists( __NAMESPACE__.'\\base64_decode' ) ) {
	function base64_decode( string $data, bool $strict = false ) :string {
		\error_clear_last();
		$result = \base64_decode( $data, $strict );
		if ( $result === false ) {
			throw UrlException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\class_implements' ) ) {
	function class_implements( $class, bool $autoload = true ) :array {
		\error_clear_last();
		$result = \class_implements( $class, $autoload );
		if ( $result === false ) {
			throw SplException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\fclose' ) ) {
	function fclose( $handle ) :void {
		\error_clear_last();
		$result = \fclose( $handle );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
	}
}

if ( !\function_exists( __NAMESPACE__.'\\file_put_contents' ) ) {
	function file_put_contents( string $filename, $data, int $flags = 0, $context = null ) :int {
		\error_clear_last();
		$result = $context !== null
			? \file_put_contents( $filename, $data, $flags, $context )
			: \file_put_contents( $filename, $data, $flags );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\fopen' ) ) {
	function fopen( string $filename, string $mode, bool $use_include_path = false, $context = null ) {
		\error_clear_last();
		$result = $context !== null
			? \fopen( $filename, $mode, $use_include_path, $context )
			: \fopen( $filename, $mode, $use_include_path );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\fread' ) ) {
	function fread( $handle, int $length ) :string {
		\error_clear_last();
		$result = \fread( $handle, $length );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\fwrite' ) ) {
	function fwrite( $handle, string $string, ?int $length = null ) :int {
		\error_clear_last();
		$result = $length !== null
			? \fwrite( $handle, $string, $length )
			: \fwrite( $handle, $string );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\hex2bin' ) ) {
	function hex2bin( string $data ) :string {
		\error_clear_last();
		$result = \hex2bin( $data );
		if ( $result === false ) {
			throw StringsException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\json_decode' ) ) {
	function json_decode( string $json, bool $assoc = false, int $depth = 512, int $options = 0 ) {
		$data = \json_decode( $json, $assoc, $depth, $options );
		if ( \json_last_error() !== \JSON_ERROR_NONE ) {
			throw JsonException::createFromPhpError();
		}
		return $data;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\json_encode' ) ) {
	function json_encode( $value, int $options = 0, int $depth = 512 ) :string {
		$result = \json_encode( $value, $options, $depth );
		if ( $result === false || \json_last_error() !== \JSON_ERROR_NONE ) {
			throw JsonException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\ksort' ) ) {
	function ksort( array &$array, int $sort_flags = \SORT_REGULAR ) :void {
		\error_clear_last();
		$result = \ksort( $array, $sort_flags );
		if ( $result === false ) {
			throw ArrayException::createFromPhpError();
		}
	}
}

if ( !\function_exists( __NAMESPACE__.'\\mkdir' ) ) {
	function mkdir( string $pathname, int $mode = 0777, bool $recursive = false, $context = null ) :void {
		\error_clear_last();
		$result = $context !== null
			? \mkdir( $pathname, $mode, $recursive, $context )
			: \mkdir( $pathname, $mode, $recursive );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
	}
}

if ( !\function_exists( __NAMESPACE__.'\\openssl_pkey_get_public' ) ) {
	function openssl_pkey_get_public( $certificate ) {
		\error_clear_last();
		$result = \openssl_pkey_get_public( $certificate );
		if ( $result === false ) {
			throw OpensslException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\parse_url' ) ) {
	function parse_url( string $url, int $component = -1 ) {
		\error_clear_last();
		$result = \parse_url( $url, $component );
		if ( $result === false ) {
			throw UrlException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\rename' ) ) {
	function rename( string $oldname, string $newname, $context = null ) :void {
		\error_clear_last();
		$result = $context !== null
			? \rename( $oldname, $newname, $context )
			: \rename( $oldname, $newname );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
	}
}

if ( !\function_exists( __NAMESPACE__.'\\rewind' ) ) {
	function rewind( $handle ) :void {
		\error_clear_last();
		$result = \rewind( $handle );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
	}
}

if ( !\function_exists( __NAMESPACE__.'\\sprintf' ) ) {
	function sprintf( string $format, ...$params ) :string {
		\error_clear_last();
		$result = $params !== []
			? \sprintf( $format, ...$params )
			: \sprintf( $format );
		if ( $result === false ) {
			throw StringsException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\tempnam' ) ) {
	function tempnam( string $dir, string $prefix ) :string {
		\error_clear_last();
		$result = \tempnam( $dir, $prefix );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
		return $result;
	}
}

if ( !\function_exists( __NAMESPACE__.'\\unlink' ) ) {
	function unlink( string $filename, $context = null ) :void {
		\error_clear_last();
		$result = $context !== null
			? \unlink( $filename, $context )
			: \unlink( $filename );
		if ( $result === false ) {
			throw FilesystemException::createFromPhpError();
		}
	}
}

if ( !\function_exists( __NAMESPACE__.'\\unpack' ) ) {
	function unpack( string $format, string $data, int $offset = 0 ) :array {
		\error_clear_last();
		$result = \unpack( $format, $data, $offset );
		if ( $result === false ) {
			throw MiscException::createFromPhpError();
		}
		return $result;
	}
}
