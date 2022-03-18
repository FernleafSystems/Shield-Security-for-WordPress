<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\{
	IsFakeWebCrawler,
	IsServerLoopback,
	IsTrustedBot,
	IsXmlrpc,
	MatchRequestIP,
	MatchRequestIPIdentity,
	MatchRequestPath,
	MatchUserAgent
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoSuchConditionHandlerException;
use FernleafSystems\Wordpress\Services\Services;

class RulesController {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return Services::Data()->getPhpVersionIsAtLeast( '7.4' );
	}

	protected function run() {
		foreach ( $this->getRules() as $rule ) {
			$this->processRule( $rule );
		}
	}

	private function processRule( RuleVO $rule ) {
		( new RuleProcessor( $rule, $this ) )->setCon( $this->getCon() )->run();
	}

	/**
	 * @return RuleVO[]
	 */
	protected function getRules() :array {
		return array_map(
			function ( $rule ) {
				return ( new RuleVO() )->applyFromArray( $rule );
			},
			json_decode( Services::WpFs()->getFileContent( path_join( __DIR__, 'rules.json' ) ), true )[ 'rules' ]
		);
	}

	/**
	 * @throws NoSuchConditionHandlerException
	 */
	public function getConditionHandler( string $condition ) :Conditions\Base {
		$theHandlerClass = null;
		foreach ( $this->enumConditionHandlers() as $class ) {
			if ( $condition === constant( sprintf( '%s::%s', $class, 'CONDITION_SLUG' ) ) ) {
				$theHandlerClass = $class;
				break;
			}
		}
		if ( empty( $theHandlerClass ) ) {
			throw new NoSuchConditionHandlerException( 'No Condition Handler available for: '.$condition );
		}
		/** @var Conditions\Base $d */
		$d = new $theHandlerClass();
		return $d->setCon( $this->getCon() );
	}

	protected function enumConditionHandlers() :array {
		return [
			IsFakeWebCrawler::class,
			IsServerLoopback::class,
			IsTrustedBot::class,
			IsXmlrpc::class,
			MatchRequestIP::class,
			MatchRequestIPIdentity::class,
			MatchRequestPath::class,
			MatchUserAgent::class,
		];
	}
}