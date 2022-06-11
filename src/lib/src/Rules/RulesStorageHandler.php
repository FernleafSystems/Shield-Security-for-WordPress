<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Builder;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\RulesControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RulesStorageHandler {

	use RulesControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function loadRules( bool $attemptRebuild = true ) :array {

		$rules = $this->loadRawFromWP();
		if ( $attemptRebuild && ( empty( $rules ) || empty( $rules[ 'rules' ] ) ) ) {
			$this->buildAndStore();
			$rules = $this->loadRules( false );
		}

		if ( !is_array( $rules[ 'rules' ] ) || empty( $rules[ 'rules' ] ) ) {
			throw new \Exception( 'No rules to load' );
		}

		return $rules;
	}

	public function buildAndStore() {
		$this->store(
			( new Builder() )
				->setRulesCon( $this->getRulesCon() )
				->run()
		);
	}

	public function build() :array {
		return ( new Builder() )
			->setRulesCon( $this->getRulesCon() )
			->run();
	}

	public function store( array $rules ) {
		$WP = Services::WpGeneral();
		$WP->updateOption( $this->getWpStorageKey(), [
			'ts'    => Services::Request()->ts(),
			'time'  => $WP->getTimeStampForDisplay( Services::Request()->ts() ),
			'rules' => array_map( function ( RuleVO $rule ) {
				return $rule->getRawData();
			}, $rules ),
		] );
	}

	private function loadRawFromWP() :array {
		$raw = Services::WpGeneral()->getOption( $this->getWpStorageKey() );
		return is_array( $raw ) ? $raw : [];
	}

	private function getWpStorageKey() :string {
		return $this->getRulesCon()->getCon()->prefix( 'rules' );
	}

	/**
	 * @deprecated 15.1
	 */
	private function loadRawFromFile() :array {
		return [];
	}

	/**
	 * @deprecated 15.1
	 */
	private function getPathToRules() :string {
		return path_join( $this->getRulesCon()->getCon()->cache_dir_handler->build(), 'rules.json' );
	}
}