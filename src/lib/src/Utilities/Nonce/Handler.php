<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Nonce;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class Handler {

	use PluginControllerConsumer;

	public function create( string $action, int $ttl = 0 ) :string {
		$nonce = hash_hmac( 'sha1', $action, $this->getCon()->getInstallationID()[ 'id' ] );
		Transient::Set( 'apto-nonce-'.$action, $nonce, $ttl );
		return $nonce;
	}

	public function verify( string $action, string $nonce ) :bool {
		$valid = hash_equals(
			(string)Transient::Get( 'apto-nonce-'.$action, '' ),
			hash_hmac( 'sha1', $action, $this->getCon()->getInstallationID()[ 'id' ] )
		);
		Transient::Delete( 'apto-nonce-'.$action );
		return $valid;
	}
}