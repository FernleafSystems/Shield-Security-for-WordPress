<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class EventsParser {

	use PluginControllerConsumer;

	public function ipBlocking() :array {
		return $this->extract( [
			'ip_offense',
			'ip_blocked',
			'conn_kill',
		] );
	}

	public function offenses() :array {
		return \array_filter( self::con()->comps->events->getEvents(), function ( array $event ) {
			return $event[ 'offense' ] ?? false;
		} );
	}

	public function plugins() :array {
		return $this->extract(
			\array_filter( $this->events(), function ( string $event ) {
				return \str_starts_with( $event, 'plugin_' );
			} )
		);
	}

	public function themes() :array {
		return $this->extract(
			\array_filter( $this->events(), function ( string $event ) {
				return \str_starts_with( $event, 'theme_' );
			} )
		);
	}

	public function accounts() :array {
		return $this->extract( [
			'user_promoted',
			'user_demoted',
			'user_deleted',
			'user_email_updated',
			'user_password_updated',
			'app_pass_created',
		] );
	}

	public function userAccess() :array {
		return $this->extract( [
			'user_login',
			'user_registered',
			'2fa_verify_success',
			'2fa_verify_fail',
			'user_hard_suspended',
			'user_hard_unsuspended',
		] );
	}

	public function security() :array {
		return \array_merge( $this->ipBlocking(), $this->offenses() );
	}

	public function wordpress() :array {
		return \array_merge( $this->plugins(), $this->themes() );
	}

	private function extract( array $events ) :array {
		return \array_intersect_key( self::con()->comps->events->getEvents(), \array_flip( $events ) );
	}

	private function events() :array {
		return \array_keys( self::con()->comps->events->getEvents() );
	}
}