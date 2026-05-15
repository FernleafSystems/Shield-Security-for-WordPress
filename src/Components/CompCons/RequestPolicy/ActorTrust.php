<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class ActorTrust {

	public bool $is_logged_in = false;

	public bool $is_security_admin = false;

	public bool $is_high_reputation_ip = false;

	public bool $is_trusted_service = false;

	public bool $is_trusted_authenticated = false;

	public int $bot_probability = 0;

	public function __construct( array $data = [] ) {
		foreach ( $data as $key => $value ) {
			if ( \property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
		$this->bot_probability = (int)\max( 0, \min( 100, $this->bot_probability ) );
		$this->is_trusted_authenticated = $this->is_logged_in
										  && (
											  $this->is_security_admin
											  || $this->is_high_reputation_ip
											  || $this->is_trusted_service
										  );
	}

	public function flags() :array {
		return [
			'is_logged_in'              => $this->is_logged_in,
			'is_security_admin'        => $this->is_security_admin,
			'is_high_reputation_ip'    => $this->is_high_reputation_ip,
			'is_trusted_service'       => $this->is_trusted_service,
			'is_trusted_authenticated' => $this->is_trusted_authenticated,
			'bot_probability'          => $this->bot_probability,
		];
	}
}
