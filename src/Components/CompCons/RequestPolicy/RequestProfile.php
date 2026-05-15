<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class RequestProfile {

	public const SURFACE_PUBLIC_READ = 'public_read';
	public const SURFACE_API_READ = 'api_read';
	public const SURFACE_AUTH_ATTEMPT = 'auth_attempt';
	public const SURFACE_CONTENT_MUTATION = 'content_mutation';
	public const SURFACE_ADMIN_MUTATION = 'admin_mutation';
	public const SURFACE_API_MUTATION = 'api_mutation';
	public const SURFACE_XMLRPC = 'xmlrpc';
	public const SURFACE_SHIELD_ACTION = 'shield_action';
	public const SURFACE_PROBE = 'probe';

	public string $method = 'GET';

	public string $type = '';

	public string $surface = self::SURFACE_PUBLIC_READ;

	public string $path = '';

	public string $rest_route = '';

	public bool $is_mutation = false;

	public bool $is_sensitive = false;

	public function __construct( array $data = [] ) {
		foreach ( $data as $key => $value ) {
			if ( \property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
		$this->method = \strtoupper( $this->method );
		$this->is_mutation = $this->isMutationMethod( $this->method );
		$this->is_sensitive = \in_array( $this->surface, [
			self::SURFACE_AUTH_ATTEMPT,
			self::SURFACE_CONTENT_MUTATION,
			self::SURFACE_ADMIN_MUTATION,
			self::SURFACE_API_MUTATION,
			self::SURFACE_XMLRPC,
			self::SURFACE_SHIELD_ACTION,
			self::SURFACE_PROBE,
		], true );
	}

	public static function isMutationMethod( string $method ) :bool {
		return \in_array( \strtoupper( $method ), [ 'POST', 'PUT', 'PATCH', 'DELETE' ], true );
	}

	public function isReadSurface() :bool {
		return \in_array( $this->surface, [ self::SURFACE_PUBLIC_READ, self::SURFACE_API_READ ], true );
	}

	public function isMutationSurface() :bool {
		return \in_array( $this->surface, [
			self::SURFACE_CONTENT_MUTATION,
			self::SURFACE_ADMIN_MUTATION,
			self::SURFACE_API_MUTATION,
			self::SURFACE_SHIELD_ACTION,
		], true );
	}
}
