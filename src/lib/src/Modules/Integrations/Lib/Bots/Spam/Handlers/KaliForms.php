<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class KaliForms extends Base {

	protected function run() {
		add_filter( 'kaliforms_before_form_process', function ( $data ) {
			if ( is_array( $data ) && empty( $data[ 'error_bag' ] ) && $this->isSpam() ) {
				$data[ 'admin_stop_execution' ] = true;
				$data[ 'admin_stop_reason' ] = __( 'Your entry appears to be spam!', 'wp-simple-firewall' );
				$data[ 'error_bag' ] = [
					__( 'SPAM Bot detected.', 'wp-simple-firewall' )
				];
			}
			return $data;
		}, 1000 );
	}

	protected function getProviderName() :string {
		return 'Kali Forms';
	}

	public static function IsProviderInstalled() :bool {
		return defined( 'KALIFORMS_PLUGIN_FILE' ) && @class_exists( '\KaliForms\Inc\KaliForms' );
	}
}