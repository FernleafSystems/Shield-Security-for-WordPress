<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RulesStorageHandler {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function loadRules( bool $attemptRebuildIfRequired = true ) :array {

		$rules = $this->loadRawFromWP();
		if ( $attemptRebuildIfRequired && ( empty( $rules ) || empty( $rules[ 'rules' ] ) ) ) {
			self::con()->rules->buildAndStore();
			$rules = $this->loadRules( false );
		}

		if ( !\is_array( $rules[ 'rules' ] ) || empty( $rules[ 'rules' ] ) ) {
			throw new \Exception( 'No rules to load' );
		}

		return $rules;
	}

	public function store( array $rules ) {

		$rulesForStorage = \array_map( function ( RuleVO $rule ) {
			return $rule->getRawData();
		}, $rules );

		if ( \serialize( $this->loadRawFromWP()[ 'rules' ] ?? '' ) !== \serialize( $rulesForStorage ) ) {
			Services::WpGeneral()->updateOption( $this->getWpStorageKey(), [
				'ts'    => Services::Request()->ts(),
				'time'  => Services::WpGeneral()->getTimeStampForDisplay( Services::Request()->ts() ),
				'rules' => $rulesForStorage,
			] );
		}
	}

	private function loadRawFromWP() :array {
		$raw = Services::WpGeneral()->getOption( $this->getWpStorageKey() );
		return \is_array( $raw ) ? $raw : [];
	}

	private function getWpStorageKey() :string {
		return self::con()->prefix( 'rules' );
	}
}