<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\TracksOptionWrites;
use FernleafSystems\Wordpress\Services\Services;

class OptsHandlerStoreSemanticsIntegrationTest extends ShieldIntegrationTestCase {

	use TracksOptionWrites;

	private array $originalOptions = [];

	public function set_up() {
		parent::set_up();
		$con = $this->requireController();
		foreach ( [
			'api_namespace_exclusions',
			'importexport_masterurl',
			'ipdetect_at',
			'instant_alert_filelocker',
			'instant_alert_vulnerabilities',
			'preferred_temp_dir',
			'tracking_permission_set_at',
		] as $key ) {
			$this->originalOptions[ $key ] = $con->opts->optGet( $key );
		}
	}

	public function tear_down() {
		$con = static::con();
		if ( $con !== null ) {
			foreach ( $this->originalOptions as $key => $value ) {
				$con->opts->optSet( $key, $value );
			}
			if ( $con->opts->hasChanges() ) {
				$con->opts->store();
			}
		}
		$this->stopTrackingOptionWrites();
		parent::tear_down();
	}

	public function test_store_heals_legacy_persisted_values_and_writes_options() :void {
		$this->primeCanonicalState();
		$this->replaceStoredOptionValues( [
			'api_namespace_exclusions' => [ 'woocommerce' ],
			'importexport_masterurl'   => 'not a valid url',
		] );
		$con = $this->requireController();

		$preCount = 0;
		$afterFlags = [];
		$preHook = function () use ( &$preCount ) :void {
			++$preCount;
		};
		$afterHook = function ( bool $hasChanges ) use ( &$afterFlags ) :void {
			$afterFlags[] = $hasChanges;
		};

		add_action( $this->hookName( 'pre_options_store' ), $preHook );
		add_action( $this->hookName( 'after_pre_options_store' ), $afterHook );
		$this->startTrackingOptionWrites( [ $this->optsAllOptionName() ] );

		$con->opts->store();

		remove_action( $this->hookName( 'pre_options_store' ), $preHook );
		remove_action( $this->hookName( 'after_pre_options_store' ), $afterHook );

		$this->assertGreaterThan( 0, \count( $this->getTrackedOptionWrites() ) );
		$this->assertSame( 1, $preCount );
		$this->assertSame( [ true ], $afterFlags );
		$this->assertSame( [ 'woocommerce', 'shield' ], $con->opts->optGet( 'api_namespace_exclusions' ) );
		$this->assertSame( '', $con->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_clean_store_fires_hooks_without_writing_options() :void {
		$this->primeCanonicalState();
		$con = $this->requireController();

		$preCount = 0;
		$afterFlags = [];
		$preHook = function () use ( &$preCount ) :void {
			++$preCount;
		};
		$afterHook = function ( bool $hasChanges ) use ( &$afterFlags ) :void {
			$afterFlags[] = $hasChanges;
		};

		add_action( $this->hookName( 'pre_options_store' ), $preHook );
		add_action( $this->hookName( 'after_pre_options_store' ), $afterHook );
		$this->startTrackingOptionWrites( [ $this->optsAllOptionName() ] );

		$con->opts->store();

		remove_action( $this->hookName( 'pre_options_store' ), $preHook );
		remove_action( $this->hookName( 'after_pre_options_store' ), $afterHook );

		$this->assertOptionWasNotWritten( $this->optsAllOptionName() );
		$this->assertSame( 1, $preCount );
		$this->assertSame( [ false ], $afterFlags );
	}

	private function primeCanonicalState() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'api_namespace_exclusions', [ 'shield' ] )
			->optSet( 'importexport_masterurl', '' )
			->optSet( 'ipdetect_at', 1 )
			->optSet( 'instant_alert_filelocker', 'disabled' )
			->optSet( 'instant_alert_vulnerabilities', 'disabled' )
			->optSet( 'preferred_temp_dir', '' )
			->optSet( 'tracking_permission_set_at', time() );
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
	}

	/**
	 * @param array<string,mixed> $values
	 */
	private function replaceStoredOptionValues( array $values ) :void {
		$con = $this->requireController();
		$all = Services::WpGeneral()->getOption( $this->optsAllOptionName() );
		if ( !\is_array( $all ) ) {
			$all = [
				'version'       => $con->cfg->version(),
				'values'        => [
					OptsHandler::TYPE_FREE => [],
					OptsHandler::TYPE_PRO  => [],
				],
				'xfer_excluded' => [],
			];
		}

		foreach ( $values as $key => $value ) {
			$all[ 'values' ][ OptsHandler::TYPE_FREE ][ $key ] = $value;
			$all[ 'values' ][ OptsHandler::TYPE_PRO ][ $key ] = $value;
		}

		Services::WpGeneral()->updateOption( $this->optsAllOptionName(), $all );
		$con->opts = new OptsHandler();
	}

	private function hookName( string $suffix ) :string {
		return $this->requireController()->prefix( $suffix );
	}

	private function optsAllOptionName() :string {
		return $this->requireController()->prefix( 'opts_all', '_' );
	}
}
