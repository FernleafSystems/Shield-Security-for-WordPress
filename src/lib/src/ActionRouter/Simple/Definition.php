<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple;

class Definition {

	public const POLICY_REQUIRE_NONCE = 'require_nonce';
	public const POLICY_MIN_CAPABILITY = 'min_capability';
	public const POLICY_REQUIRE_SECURITY_ADMIN = 'require_security_admin';
	public const POLICY_BYPASS_IP_BLOCK = 'bypass_ip_block';

	private string $slug;

	/**
	 * @var callable
	 */
	private $handler;

	private array $defaults;

	private array $requiredDataKeys;

	private array $policies;

	/**
	 * @param array{defaults?:array,required_data?:array,policies?:array} $params
	 */
	public function __construct( string $slug, callable $handler, array $params = [] ) {
		$this->slug = $slug;
		$this->handler = $handler;
		$this->defaults = (array)( $params[ 'defaults' ] ?? [] );
		$this->requiredDataKeys = (array)( $params[ 'required_data' ] ?? [] );
		$this->policies = (array)( $params[ 'policies' ] ?? [] );
	}

	public function slug() :string {
		return $this->slug;
	}

	public function handler() :callable {
		return $this->handler;
	}

	public function defaults() :array {
		return $this->defaults;
	}

	public function requiredDataKeys() :array {
		return $this->requiredDataKeys;
	}

	public function policies() :array {
		return $this->policies;
	}
}
