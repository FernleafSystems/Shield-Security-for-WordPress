<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class KaliForms extends Base {

	protected function run() {
		add_filter( 'kaliforms_before_form_process', function ( $data ) {
			if ( \is_array( $data ) && empty( $data[ 'error_bag' ] ) && $this->isBotBlockRequired() ) {
				$data[ 'admin_stop_execution' ] = true;
				$data[ 'admin_stop_reason' ] = $this->getCommonSpamMessage();
				$data[ 'error_bag' ] = [
					__( 'SPAM Bot detected.', 'wp-simple-firewall' )
				];
			}
			return $data;
		}, 1000 );
	}
}