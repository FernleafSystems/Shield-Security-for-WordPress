<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Services\Services;

class IpIdentify {

	use IpAddressConsumer;

	const UNKNOWN = 'unknown';
	const VISITOR = 'visitor';
	const THIS_SERVER = 'server';
	const APPLE = 'apple';
	const BAIDU = 'baidu';
	const BING = 'bing';
	const CLOUDFLARE = 'cloudflare';
	const DUCKDUCKGO = 'duckduckgo';
	const GOOGLE = 'google';
	const HUAWEI = 'huawei';
	const ICONTROLWP = 'icontrolwp';
	const MANAGEWP = 'managewp';
	const PINGDOM = 'pingdom';
	const STATUSCAKE = 'statuscake';
	const SEMRUSH = 'semrush';
	const STRIPE = 'stripe';
	const UPTIMEROBOT = 'uptimerobot';
	const YAHOO = 'yahoo';
	const YANDEX = 'yandex';

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	public function run() :array {
		$srvIP = Services::IP();
		$srvProviders = Services::ServiceProviders();

		$ip = $this->getIP();
		if ( !Services::IP()->isValidIp( $ip ) ) {
			throw new \Exception( "A valid IP address was not provided." );
		}

		if ( $srvIP->checkIp( $ip, $srvIP->getRequestIp() ) ) {
			$is = self::VISITOR;
		}
		elseif ( $srvIP->checkIp( $ip, $srvIP->getServerPublicIPs() ) ) {
			$is = self::THIS_SERVER;
		}
		elseif ( $srvProviders->isIp_AppleBot( $ip, '' ) ) {
			$is = self::APPLE;
		}
		elseif ( $srvProviders->isIp_BaiduBot( $ip, '' ) ) {
			$is = self::BAIDU;
		}
		elseif ( $srvProviders->isIp_BingBot( $ip, '' ) ) {
			$is = self::BING;
		}
		elseif ( $srvProviders->isIp_Cloudflare( $ip ) ) {
			$is = self::CLOUDFLARE;
		}
		elseif ( $srvProviders->isIp_DuckDuckGoBot( $ip, '' ) ) {
			$is = self::DUCKDUCKGO;
		}
		elseif ( $srvProviders->isIp_HuaweiBot( $ip, '' ) ) {
			$is = self::HUAWEI;
		}
		elseif ( $srvProviders->isIp_iControlWP( $ip ) ) {
			$is = self::ICONTROLWP;
		}
		elseif ( $srvProviders->isIp_iControlWP( $ip, '' ) ) { // TODO
			$is = self::MANAGEWP;
		}
		elseif ( $srvProviders->isIp_Pingdom( $ip, '' ) ) {
			$is = self::PINGDOM;
		}
		elseif ( $srvProviders->isIp_SemRush( $ip, '' ) ) {
			$is = self::SEMRUSH;
		}
		elseif ( $srvProviders->isIp_Statuscake( $ip, '' ) ) {
			$is = self::STATUSCAKE;
		}
		elseif ( $srvProviders->isIp_Stripe( $ip, '' ) ) {
			$is = self::STRIPE;
		}
		elseif ( $srvProviders->isIp_UptimeRobot( $ip, '' ) ) {
			$is = self::UPTIMEROBOT;
		}
		elseif ( $srvProviders->isIp_YahooBot( $ip, '' ) ) {
			$is = self::YAHOO;
		}
		elseif ( $srvProviders->isIp_YandexBot( $ip, '' ) ) {
			$is = self::YANDEX;
		}
		else {
			$is = self::UNKNOWN;
		}

		return [ $is => $this->getNames()[ $is ] ];
	}

	public function getNames() :array {
		return [
			self::UNKNOWN     => 'Unknown',
			self::THIS_SERVER => 'Server',
			self::VISITOR     => 'You',
			self::APPLE       => 'AppleBot',
			self::BAIDU       => 'BaiduBot',
			self::BING        => 'BingBot',
			self::CLOUDFLARE  => 'CloudFlare',
			self::DUCKDUCKGO  => 'DuckDuckGoBot',
			self::GOOGLE      => 'GoogleBot',
			self::HUAWEI      => 'Huawei/PetalBot',
			self::ICONTROLWP  => 'iControlWP',
			self::MANAGEWP    => 'ManageWP',
			self::PINGDOM     => 'Pingdom',
			self::SEMRUSH     => 'SEMRush',
			self::STATUSCAKE  => 'StatusCake',
			self::STRIPE      => 'Stripe',
			self::UPTIMEROBOT => 'UptimeRobot',
			self::YAHOO       => 'YahooBot',
			self::YANDEX      => 'YandexBot',
		];
	}

	public function getName( string $id ) :string {
		return $this->getNames()[ $id ];
	}
}