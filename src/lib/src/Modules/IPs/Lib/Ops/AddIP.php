<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\{
	Ops as IpRulesDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;
use IPLib\Range\Type;

class AddIP {

	use Modules\ModConsumer;
	use Modules\IPs\Components\IpAddressConsumer;

	/**
	 * @throws \Exception
	 */
	public function toAutoBlacklist() :IpRulesDB\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();
		$req = Services::Request();

		try {
			$IP = $this->add( $dbh::T_AUTO_BLACK, [
				'label'          => 'auto',
				'last_access_at' => $req->ts(),
			] );
			$this->getCon()->fireEvent( 'ip_block_auto', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		}
		catch ( \Exception $e ) {
			$IP = ( new LookupIP() )
				->setMod( $mod )
				->setIP( $this->getIP() )
				->setListTypeBlock()
				->lookup( false );
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();

		if ( !$this->getCon()->isPremiumActive() ) {
			throw new \Exception( __( 'Sorry, this is a PRO-only feature.', 'wp-simple-firewall' ) );
		}

		$IP = $this->add( $dbh::T_MANUAL_BLACK, [
			'label'      => $label,
			'blocked_at' => Services::Request()->ts(),
		] );
		$this->getCon()->fireEvent( 'ip_block_manual', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );

		return $IP;
	}

	/**
	 * @throws \Exception
	 */
	public function toManualWhitelist( string $label = '' ) :IpRulesDB\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();

		$IP = $this->add( $dbh::T_MANUAL_WHITE, [
			'label' => $label
		] );
		$this->getCon()->fireEvent( 'ip_bypass_add', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );

		return $IP;
	}

	/**
	 * @throws \Exception
	 */
	public function toCrowdsecBlocklist() :IpRulesDB\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();
		return $this->add( $dbh::T_CROWDSEC, [
			'blocked_at' => Services::Request()->ts(),
		] );
	}

	/**
	 * @throws \Exception
	 */
	private function add( string $listType, array $data = [] ) :IpRulesDB\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();

		$ip = $this->getIP();
		$parsedRange = \IPLib\Factory::parseRangeString( $ip );
		if ( empty( $parsedRange ) ) {
			throw new \Exception( sprintf( "Invalid IP address or IP Range: %s", $ip ) );
		}
		if ( !in_array( $parsedRange->getRangeType(), [ Type::T_PUBLIC, Type::T_PRIVATENETWORK ] ) ) {
			throw new \Exception( sprintf( "A non-public/private IP address provided: %s", $ip ) );
		}
		if ( $parsedRange->getSize() > 1 && $listType === $dbh::T_AUTO_BLACK ) {
			throw new \Exception( "Automatic blocking of IP ranges isn't supported at this time." );
		}

		// Never block our own server IP
		if ( in_array( $listType, [ $dbh::T_AUTO_BLACK, $dbh::T_MANUAL_BLACK, $dbh::T_CROWDSEC ] ) ) {
			foreach ( Services::IP()->getServerPublicIPs() as $serverPublicIP ) {
				$serverAddress = \IPLib\Factory::parseAddressString( $serverPublicIP );
				if ( $parsedRange->contains( $serverAddress ) ) {
					throw new \Exception( "Forbidden to blacklist server's public IP." );
				}
			}
		}

		$ipLookerUpper = ( new LookupIP() )
			->setMod( $mod )
			->setIP( $this->getIP() );

		switch ( $listType ) {

			case $dbh::T_CROWDSEC:
				$IP = $ipLookerUpper->setListTypeCrowdsec()->lookup();
				if ( !empty( $IP ) ) {
					throw new \Exception( sprintf( 'Crowdsec IP (%s) already present.', $ip ) );
				}
				break;

			case $dbh::T_MANUAL_WHITE:
				$IP = $ipLookerUpper->setListTypeBypass()->lookup();
				if ( !empty( $IP ) ) {
					throw new \Exception( sprintf( 'IP (%s) is already on bypass list.', $ip ) );
				}
				break;

			case $dbh::T_AUTO_BLACK:
			case $dbh::T_MANUAL_BLACK:
				$IP = $ipLookerUpper->setListTypeBlock()->lookup();
				if ( !empty( $IP ) && $IP->type === $listType ) {
					throw new \Exception( sprintf( 'IP (%s) is already on that block list.', $ip ) );
				}
				break;

			default:
				throw new \Exception( sprintf( "An invalid list type provided: %s", $listType ) );
		}

		$ipRecord = ( new Modules\Data\DB\IPs\IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $this->getIP() );

		/** @var IpRulesDB\Record $tmp */
		$tmp = $dbh->getRecord();
		$tmp->applyFromArray( $data );
		$tmp->ip_ref = $ipRecord->id;
		$tmp->type = $listType;
		$tmp->label = preg_replace( '/[^\sa-z0-9_\-]/i', '', $tmp->label );
		$tmp->cidr = explode( '/', $parsedRange->asSubnet()->toString(), 2 )[ 1 ];

		if ( $dbh->getQueryInserter()->insert( $tmp ) ) {
			/** @var IpRulesDB\Record $ipRuleRecord */
			$ipRuleRecord = $dbh->getQuerySelector()
								->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
		}

		if ( empty( $ipRuleRecord ) ) {
			throw new \Exception( "IP Rule couldn't be added to the database." );
		}

		return $ipRuleRecord;
	}
}