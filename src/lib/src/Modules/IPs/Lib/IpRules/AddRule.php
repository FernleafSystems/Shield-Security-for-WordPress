<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\{
	Ops as IpRulesDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;
use IPLib\Factory;
use IPLib\Range\Type;

class AddRule {

	use Modules\ModConsumer;
	use Modules\IPs\Components\IpAddressConsumer;

	public const MOD = Modules\IPs\ModCon::SLUG;

	/**
	 * @throws \Exception
	 */
	public function toAutoBlacklist() :IpRulesDB\Record {
		try {
			$IP = $this->add( IpRulesDB\Handler::T_AUTO_BLOCK, [
				'label'          => 'auto',
				'last_access_at' => Services::Request()->ts(),
			] );
			$this->getCon()->fireEvent( 'ip_block_auto', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		}
		catch ( \Exception $e ) {
			$IP = ( new IpRuleStatus( $this->getIP() ) )
				->setMod( $this->getMod() )
				->getRuleForAutoBlock();
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
		$IP = $this->add( IpRulesDB\Handler::T_MANUAL_BLOCK, [
			'label' => $label,
		] );
		$this->getCon()->fireEvent( 'ip_block_manual', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
		return $IP;
	}

	/**
	 * @throws \Exception
	 */
	public function toManualWhitelist( string $label = '' ) :IpRulesDB\Record {
		$IP = $this->add( IpRulesDB\Handler::T_MANUAL_BYPASS, [
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();

		$ip = $this->getIP();
		$parsedRange = Factory::parseRangeString( $ip );
		if ( empty( $parsedRange ) ) {
			throw new \Exception( sprintf( "Invalid IP address or IP Range: %s", $ip ) );
		}
		if ( !in_array( $parsedRange->getRangeType(), [ Type::T_PUBLIC, Type::T_PRIVATENETWORK ] ) ) {
			throw new \Exception( sprintf( "A non-public/private IP address provided: %s", $ip ) );
		}
		if ( $parsedRange->getSize() > 1 && $listType === $dbh::T_AUTO_BLOCK ) {
			throw new \Exception( "Automatic blocking of IP ranges isn't supported at this time." );
		}

		// Never block our own server IP
		if ( in_array( $listType, [ $dbh::T_AUTO_BLOCK, $dbh::T_MANUAL_BLOCK, $dbh::T_CROWDSEC ] ) ) {
			foreach ( Services::IP()->getServerPublicIPs() as $serverPublicIP ) {
				$serverAddress = Factory::parseAddressString( $serverPublicIP );
				if ( !empty( $serverAddress ) && $parsedRange->contains( $serverAddress ) ) {
					throw new \Exception( "Blocking the webserver's public IP address is currently prohibited." );
				}
			}
		}

		$ruleStatus = new IpRuleStatus( $this->getIP() );

		switch ( $listType ) {

			case $dbh::T_MANUAL_BYPASS:
				if ( $ruleStatus->isBypass() ) {
					throw new \Exception( sprintf( 'IP (%s) is already on the bypass list.', $ip ) );
				}
				if ( $ruleStatus->isAutoBlacklisted() ) {
					( new DeleteRule() )->byRecord( $ruleStatus->getRuleForAutoBlock() );
				}
				if ( $ruleStatus->hasManualBlock() ) {
					foreach ( $ruleStatus->getRulesForManualBlock() as $rule ) {
						if ( !$rule->is_range ) {
							( new DeleteRule() )->byRecord( $rule );
						}
					}
				}
				if ( $ruleStatus->hasCrowdsecBlock() ) {
					foreach ( $ruleStatus->getRulesForCrowdsec() as $rule ) {
						if ( !$rule->is_range ) {
							( new DeleteRule() )->byRecord( $rule );
						}
					}
				}
				break;

			case $dbh::T_CROWDSEC:
				if ( $ruleStatus->isBypass() ) {
					throw new \Exception( sprintf( "Not allowed to add CrowdSec rule for IP (%s) when it's whitelisted.", $ip ) );
				}
				if ( $ruleStatus->hasManualBlock() ) {
					throw new \Exception( sprintf( "IP (%s) is already manually blocked so we don't duplicate.", $ip ) );
				}
				if ( $ruleStatus->hasCrowdsecBlock() ) {
					throw new \Exception( sprintf( 'IP (%s) is already on the CrowdSec list.', $ip ) );
				}
				if ( $ruleStatus->isAutoBlacklisted() ) {
					( new DeleteRule() )->byRecord( $ruleStatus->getRuleForAutoBlock() );
				}
				break;

			// An IP can never be added unless it doesn't exist on any other list.
			case $dbh::T_AUTO_BLOCK:
				if ( $ruleStatus->isBypass() ) {
					throw new \Exception( sprintf( 'IP (%s) is already on the bypass list.', $ip ) );
				}
				if ( $ruleStatus->hasCrowdsecBlock() ) {
					throw new \Exception( sprintf( 'IP (%s) is currently on the crowdsec list.', $ip ) );
				}
				if ( $ruleStatus->hasManualBlock() ) {
					throw new \Exception( sprintf( 'IP (%s) is already manually blocked.', $ip ) );
				}
				if ( $ruleStatus->isAutoBlacklisted() ) {
					throw new \Exception( sprintf( 'IP (%s) is already on the auto-blocklist.', $ip ) );
				}
				break;

			case $dbh::T_MANUAL_BLOCK:
				if ( $ruleStatus->isBypass() ) {
					throw new \Exception( sprintf( 'IP (%s) is already on the bypass list.', $ip ) );
				}
				if ( $ruleStatus->hasManualBlock() ) {
					throw new \Exception( sprintf( 'IP (%s) is already manually blocked.', $ip ) );
				}
				if ( $ruleStatus->hasCrowdsecBlock() ) {
					( new DeleteRule() )->byRecords( $ruleStatus->getRulesForCrowdsec() );
				}

				// 1. You can manually block an IP on the Auto list (it'll be replaced)
				if ( $ruleStatus->isAutoBlacklisted() ) {
					( new DeleteRule() )->byRecord( $ruleStatus->getRuleForAutoBlock() );
				}

				if ( $parsedRange->getSize() > 1 && $ruleStatus->hasManualBlock() ) {
					foreach ( $ruleStatus->getRulesForManualBlock() as $existingRule ) {
						$parsedExistingRange = Factory::parseRangeString( $existingRule->ipAsSubnetRange() );

						// 2. If you're manually adding a range and an existing range is covered by that range => no action taken.
						if ( $parsedExistingRange->asSubnet()->toString() === $parsedRange->asSubnet()->toString() ) {
							throw new \Exception( sprintf( 'IP Range (%s) is already manually blocked.', $ip ) );
						}

						// 3. If you're manually adding a range and an existing single entry or range is covered by that range, it is replaced.
						if ( !$existingRule->is_range || $parsedRange->containsRange( $parsedExistingRange ) ) {
							( new DeleteRule() )->byRecord( $existingRule );
						}
					}
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
		$tmp->cidr = explode( '/', $parsedRange->asSubnet()->toString(), 2 )[ 1 ];
		$tmp->is_range = $parsedRange->getSize() > 1;
		$tmp->type = $listType;
		$tmp->label = preg_replace( '/[^\sa-z0-9_\-]/i', '', $tmp->label );
		if ( $tmp->blocked_at == 0 && in_array( $listType, [ $dbh::T_MANUAL_BLOCK, $dbh::T_CROWDSEC ] ) ) {
			$tmp->blocked_at = Services::Request()->ts();
		}

		if ( $dbh->getQueryInserter()->insert( $tmp ) ) {
			/** @var IpRulesDB\Record $ipRuleRecord */
			$ipRuleRecord = $dbh->getQuerySelector()
								->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
		}

		if ( empty( $ipRuleRecord ) ) {
			throw new \Exception( "IP Rule couldn't be added to the database." );
		}

		$ruleStatus::ClearStatusForIP( $ip );

		return $ipRuleRecord;
	}
}