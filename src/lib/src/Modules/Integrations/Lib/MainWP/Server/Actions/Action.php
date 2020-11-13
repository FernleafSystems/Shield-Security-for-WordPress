<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use MainWP\Dashboard\MainWP_Connect;

class Action {

	use ModConsumer;
	use MainWP\Common\Consumers\MWPSiteConsumer;

	/**
	 * @param string $actionToExecute
	 * @param array  $params
	 * @return array
	 * @throws \Exception
	 */
	public function run( string $actionToExecute, array $params = [] ) :array {
		$info = MainWP_Connect::fetch_url_authed(
			$this->getMwpSite()->siteobj,
			'extra_execution',
			[
				$this->getCon()->prefix( 'mainwp-action' ) => $actionToExecute,
				$this->getCon()->prefix( 'mainwp-params' ) => $params
			]
		);

		$key = $this->getCon()->prefix( 'mainwp-action' );
		if ( empty( $info ) || !is_array( $info ) || !isset( $info[ $key ] ) ) {
			throw new \Exception( 'Empty response from client site' );
		}

		$decoded = json_decode( $info[ $key ], true );
		if ( empty( $decoded ) || !is_array( $decoded ) ) {
			throw new \Exception( 'Invalid response from client site' );
		}

		return $decoded;
	}
}
