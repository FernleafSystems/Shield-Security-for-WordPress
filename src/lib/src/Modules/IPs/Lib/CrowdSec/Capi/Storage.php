<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi;

use AptowebDeps\CrowdSec\CapiClient\Storage\StorageInterface;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Storage implements StorageInterface {

	use PluginControllerConsumer;

	public function retrieveMachineId() :?string {
		return $this->getAuth()[ 'machine_id' ] ?? null;
	}

	public function retrievePassword() :?string {
		return $this->getAuth()[ 'password' ] ?? null;
	}

	public function retrieveScenarios() :?array {
		$con = self::con();
		$scenarios = $con->comps->license->getLicense()->crowdsec[ 'scenarios' ] ?? [];
		if ( self::con()->isPremiumActive() ) {
			$filteredScenarios = apply_filters( 'shield/crowdsec/login_scenarios', $scenarios );
			if ( !empty( $filteredScenarios ) && \is_array( $filteredScenarios ) ) {
				$scenarios = $filteredScenarios;
			}
		}
		return empty( $scenarios ) ? $con->cfg->configuration->def( 'crowdsec' )[ 'scenarios' ][ 'free' ] : $scenarios;
	}

	public function retrieveToken() :?string {
		return $this->getAuth()[ 'auth_token' ] ?? null;
	}

	public function storeMachineId( string $machineId ) :bool {
		return $this->setAuthItem( 'machine_id', $machineId );
	}

	public function storePassword( string $password ) :bool {
		return $this->setAuthItem( 'password', $password );
	}

	public function storeScenarios( array $scenarios ) :bool {
		return false;
	}

	public function storeToken( string $token ) :bool {
		return $this->setAuthItem( 'auth_token', $token );
	}

	public function getAuths() :array {
		$auths = Services::WpGeneral()->getOption( self::con()->prefix( 'cs_auths' ) );
		return ( empty( $auths ) || !\is_array( $auths ) ) ? self::con()->comps->crowdsec->cfg()->cs_auths : $auths;
	}

	public function getAuth() :array {
		$auths = $this->getAuths();

		$url = Services::WpGeneral()->getWpUrl();
		$auths[ $url ] = \array_merge( [
			'url'              => $url,
			'auth_token'       => '',
			'auth_start_at'    => 0,
			'auth_expire'      => '',
			'machine_enrolled' => false,
			'enrolled_id'      => '',
			'machine_id'       => '',
			'password'         => '',
		], $auths[ $url ] ?? [] );
		Services::WpGeneral()->updateOption( self::con()->prefix( 'cs_auths' ), $auths );

		return $auths[ $url ];
	}

	private function setAuthItem( string $item, $value ) :bool {
		$auth = $this->getAuth();
		$auth[ $item ] = $value;
		return $this->storeAuth( $auth );
	}

	public function storeAuth( array $auth ) :bool {
		if ( !empty( $auth[ 'url' ] ) ) {
			$auths = $this->getAuths();
			$auths[ $auth[ 'url' ] ] = $auth;
			$auths = \array_filter( $auths, function ( $auth ) {
				return empty( $auth[ 'auth_expire' ] )
					   || Services::Request()->ts() - $auth[ 'auth_expire' ] < \WEEK_IN_SECONDS*12;
			} );
			Services::WpGeneral()->updateOption( self::con()->prefix( 'cs_auths' ), $auths );

			$cfg = self::con()->comps->crowdsec->cfg();
			$cfg->cs_auths = $auths;
			self::con()->comps->crowdsec->storeCfg( $cfg );
		}
		return true;
	}
}