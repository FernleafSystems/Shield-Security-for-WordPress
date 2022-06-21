<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Ajax;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

class PerformSiteAction {

	use MainWP\Common\Consumers\MWPSiteConsumer;
	use Shield\Modules\ModConsumer;

	public function run( string $action ) :array {
		try {
			$resp = [
				'success' => true,
				'message' => $this->{$action}()
			];
		}
		catch ( \Exception $e ) {
			$resp = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		if ( !in_array( $action, [ 'sync' ] ) ) {
			$this->getPluginActioner()->sync();
		}

		$resp[ 'page_reload' ] = true;
		return $resp;
	}

	/**
	 * @throws \Exception
	 */
	private function activate() :string {
		if ( !$this->getPluginActioner()->activate() ) {
			throw new \Exception( sprintf( __( 'Failed to activate %s plugin.' ), $this->getCon()->getHumanName() ) );
		}
		return sprintf( __( 'Successfully activated %s plugin.', 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}

	/**
	 * @throws \Exception
	 */
	private function deactivate() :string {
		if ( !$this->getPluginActioner()->deactivate() ) {
			throw new \Exception( sprintf( __( 'Failed to deactivate %s plugin.' ), $this->getCon()->getHumanName() ) );
		}
		return sprintf( __( 'Successfully deactivated %s plugin.', 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}

	/**
	 * @throws \Exception
	 */
	private function install() :string {
		if ( !$this->getPluginActioner()->install() ) {
			throw new \Exception( sprintf( __( 'Failed to install %s plugin.' ), $this->getCon()->getHumanName() ) );
		}
		return sprintf( __( 'Successfully installed %s plugin.', 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}

	/**
	 * @throws \Exception
	 */
	private function license() :string {
		$resp = $this->getApiActioner()->licenseCheck();
		if ( empty( $resp[ 'success' ] ) ) {
			throw new \Exception( $resp[ 'message' ] );
		}
		return $resp[ 'message' ];
	}

	/**
	 * @throws \Exception
	 */
	private function mwp() :string {
		$resp = $this->getApiActioner()->mwpEnable();
		if ( empty( $resp[ 'success' ] ) ) {
			throw new \Exception( $resp[ 'message' ] );
		}
		return $resp[ 'message' ];
	}

	/**
	 * @throws \Exception
	 */
	private function sync() :string {
		if ( !$this->getPluginActioner()->sync() ) {
			throw new \Exception( sprintf( __( 'Failed to sync with %s plugin.' ), $this->getCon()->getHumanName() ) );
		}
		return sprintf( __( 'Successfully synced with %s plugin.', 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}

	private function getPluginActioner() :Server\Actions\ShieldPluginAction {
		return ( new Server\Actions\ShieldPluginAction() )
			->setMod( $this->getMod() )
			->setMwpSite( $this->getMwpSite() );
	}

	private function getApiActioner() :Server\Actions\ShieldApiAction {
		return ( new Server\Actions\ShieldApiAction() )
			->setMod( $this->getMod() )
			->setMwpSite( $this->getMwpSite() );
	}
}