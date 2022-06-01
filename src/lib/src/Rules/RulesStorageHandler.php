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
		if ( empty( $rules ) ) {
			$rules = $this->loadRawFromFile();
		}

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

	public function store( array $rules ) :bool {
		$WP = Services::WpGeneral();
		$req = Services::Request();

		$data = [
			'ts'    => $req->ts(),
			'time'  => $WP->getTimeStampForDisplay( $req->ts() ),
			'rules' => array_map( function ( RuleVO $rule ) {
				return $rule->getRawData();
			}, $rules ),
		];

		$WP->updateOption( $this->getWpStorageKey(), $data );
		return Services::WpFs()->putFileContent( $this->getPathToRules(), wp_json_encode( $data ) );
	}

	private function loadRawFromWP() :array {
		$raw = Services::WpGeneral()->getOption( $this->getWpStorageKey() );
		return is_array( $raw ) ? $raw : [];
	}

	private function loadRawFromFile() :array {
		$rules = [];

		$FS = Services::WpFs();
		if ( $FS->exists( $this->getPathToRules() ) ) {
			$content = $FS->getFileContent( $this->getPathToRules() );
			if ( !empty( $content ) ) {
				$decoded = @json_decode( $content, true );
				if ( is_array( $decoded ) ) {
					$rules = $decoded;
				}
			}
		}

		return $rules;
	}

	private function getPathToRules() :string {
		return path_join( $this->getRulesCon()->getCon()->cache_dir_handler->build(), 'rules.json' );
	}

	private function getWpStorageKey() :string {
		return $this->getRulesCon()->getCon()->prefix( 'rules' );
	}
}