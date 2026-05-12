<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib;

use FernleafSystems\Wordpress\Services\Services;

class ResolveUserLookup {

	public function resolve( string $lookup ) :?\WP_User {
		$lookup = $this->normalizeLookup( $lookup );

		if ( empty( $lookup ) ) {
			return null;
		}

		if ( \ctype_digit( $lookup ) ) {
			return $this->getUserById( (int)$lookup );
		}

		if ( $this->isValidEmail( $lookup ) ) {
			return $this->getUserByEmail( $lookup );
		}

		return $this->getUserByUsername( $lookup );
	}

	protected function normalizeLookup( string $lookup ) :string {
		return \trim( sanitize_text_field( $lookup ) );
	}

	protected function isValidEmail( string $lookup ) :bool {
		return Services::Data()->validEmail( $lookup );
	}

	protected function getUserById( int $id ) :?\WP_User {
		return $this->normalizeResult( Services::WpUsers()->getUserById( $id ) );
	}

	protected function getUserByEmail( string $email ) :?\WP_User {
		return $this->normalizeResult( Services::WpUsers()->getUserByEmail( $email ) );
	}

	protected function getUserByUsername( string $username ) :?\WP_User {
		return $this->normalizeResult( Services::WpUsers()->getUserByUsername( $username ) );
	}

	private function normalizeResult( $user ) :?\WP_User {
		return $user instanceof \WP_User ? $user : null;
	}
}
