<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 16.0
 */
class AddIP {

	use Modules\ModConsumer;
	use Modules\IPs\Components\IpAddressConsumer;

	/**
	 * @throws \Exception
	 */
	public function toAutoBlacklist() :IpRulesDB\Record {
		try {
			$IP = $this->add( IpRulesDB\Handler::T_AUTO_BLACK, [
				'label'          => 'auto',
				'last_access_at' => Services::Request()->ts(),
			] );
			$this->getCon()->fireEvent( 'ip_block_auto', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		}
		catch ( \Exception $e ) {
		}

		if ( empty( $IP ) ) {
			throw new \Exception( "Couldn't create Auto-blacklist IP rule record." );
		}

		return $IP;
	}

	/**
	 * @throws \Exception
	 */
	public function toManualBlacklist( string $label = '' ) :IpRulesDB\Record {
		$IP = $this->add( IpRulesDB\Handler::T_MANUAL_BLACK, [
			'label' => $label,
		] );
		$this->getCon()->fireEvent( 'ip_block_manual', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		return $IP;
	}

	/**
	 * @throws \Exception
	 */
	public function toManualWhitelist( string $label = '' ) :IpRulesDB\Record {
		$IP = $this->add( IpRulesDB\Handler::T_MANUAL_WHITE, [
			'label' => $label
		] );
		$this->getCon()->fireEvent( 'ip_bypass_add', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		return $IP;
	}

	/**
	 * @throws \Exception
	 */
	public function toCrowdsecBlocklist() :IpRulesDB\Record {
		return $this->add( IpRulesDB\Handler::T_CROWDSEC );
	}

	/**
	 * @throws \Exception
	 */
	private function add( string $listType, array $data = [] ) :IpRulesDB\Record {
		throw new \Exception( "Deprecated." );
	}
}