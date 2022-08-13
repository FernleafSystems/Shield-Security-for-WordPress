<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\FindIpRuleRecords;

abstract class Base extends Process {

	/**
	 * @throws ApiException
	 */
	protected function getIpData( string $ip, string $list ) :array {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon $mod */
		$mod = $this->getMod();

		$retriever = ( new FindIpRuleRecords() )
			->setMod( $mod )
			->setIP( $ip );
		if ( $list === 'block' ) {
			$retriever->setListTypeBlock();
		}
		elseif ( $list === 'bypass' ) {
			$retriever->setListTypeBypass();
		}
		else {
			$retriever->setListTypeCrowdsec();
		}

		$IP = $retriever->firstSingle();
		if ( empty( $IP ) ) {
			throw new ApiException( 'IP address not found on list' );
		}

		return array_intersect_key(
			$IP->getRawData(),
			array_flip( [
				'ip',
				'label',
				'list',
				'transgressions',
				'blocked_at',
			] )
		);
	}
}