<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\InvestigateLookupSelect
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateLookupSelectIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	public function test_short_search_term_returns_empty_results_for_ip() :void {
		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'ip',
			'search'  => 'a',
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( [], $payload[ 'results' ] ?? [] );
	}

	public function test_two_character_ip_search_returns_empty_results() :void {
		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'ip',
			'search'  => '21',
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( [], $payload[ 'results' ] ?? [] );
	}

	public function test_user_lookup_allows_single_character_search() :void {
		$userLogin = 'a_lookup_user_'.\wp_rand( 1000, 9999 );
		$email = $userLogin.'@example.com';
		$userId = \wp_create_user( $userLogin, \wp_generate_password( 24, true, true ), $email );
		$this->assertIsInt( $userId );
		$this->assertGreaterThan( 0, $userId );

		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'user',
			'search'  => 'a',
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$results = (array)( $payload[ 'results' ] ?? [] );
		$this->assertNotEmpty( $results );
		$this->assertTrue(
			$this->resultsContainId( $results, (string)$userId ),
			'Expected user lookup to include the created user when searching with a single character.'
		);
		$this->assertResultsHaveSelect2Shape( $results );
	}

	public function test_user_lookup_returns_matching_user_result_shape() :void {
		$userLogin = 'investigate_lookup_user_'.\wp_rand( 1000, 9999 );
		$email = $userLogin.'@example.com';
		$userId = \wp_create_user( $userLogin, \wp_generate_password( 24, true, true ), $email );
		$this->assertIsInt( $userId );
		$this->assertGreaterThan( 0, $userId );
		\wp_update_user( [
			'ID'           => $userId,
			'display_name' => 'Lookup Display '.\wp_rand( 1000, 9999 ),
		] );

		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'user',
			'search'  => 'lookup_user',
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$results = (array)( $payload[ 'results' ] ?? [] );
		$this->assertNotEmpty( $results );
		$this->assertTrue(
			$this->resultsContainId( $results, (string)$userId ),
			'Expected user lookup results to include the created user ID.'
		);
		$result = $this->findResultById( $results, (string)$userId );
		$this->assertIsArray( $result );
		$this->assertSame( \sprintf( '[ID:%d] %s | %s', $userId, $userLogin, $email ), (string)( $result[ 'text' ] ?? '' ) );
		$this->assertResultsHaveSelect2Shape( $results );
	}

	public function test_user_numeric_lookup_prioritises_exact_id_result() :void {
		$exactUserLogin = 'investigate_exact_'.\wp_rand( 1000, 9999 );
		$exactUserId = \wp_create_user(
			$exactUserLogin,
			\wp_generate_password( 24, true, true ),
			$exactUserLogin.'@example.com'
		);
		$this->assertIsInt( $exactUserId );
		$this->assertGreaterThan( 0, $exactUserId );

		$numericTerm = (string)$exactUserId;
		$noiseLogin = 'investigate_noise_'.$numericTerm.'_'.\wp_rand( 1000, 9999 );
		$noiseUserId = \wp_create_user(
			$noiseLogin,
			\wp_generate_password( 24, true, true ),
			$noiseLogin.'@example.com'
		);
		$this->assertIsInt( $noiseUserId );
		$this->assertGreaterThan( 0, $noiseUserId );

		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'user',
			'search'  => $numericTerm,
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$results = (array)( $payload[ 'results' ] ?? [] );
		$this->assertNotEmpty( $results );
		$this->assertSame( (string)$exactUserId, (string)( $results[ 0 ][ 'id' ] ?? '' ) );
		$this->assertResultsHaveSelect2Shape( $results );
	}

	public function test_user_numeric_lookup_falls_back_to_partial_text_matching() :void {
		$numericTerm = (string)\wp_rand( 50000, 99999 );
		$login = 'investigate_numeric_'.$numericTerm.'_'.\wp_rand( 1000, 9999 );
		$userId = \wp_create_user(
			$login,
			\wp_generate_password( 24, true, true ),
			$login.'@example.com'
		);
		$this->assertIsInt( $userId );
		$this->assertGreaterThan( 0, $userId );
		$this->assertNull( Services::WpUsers()->getUserById( (int)$numericTerm ) );

		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'user',
			'search'  => $numericTerm,
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$results = (array)( $payload[ 'results' ] ?? [] );
		$this->assertNotEmpty( $results );
		$this->assertTrue(
			$this->resultsContainId( $results, (string)$userId ),
			'Expected numeric user lookup to include a user found via partial text matching.'
		);
		$this->assertResultsHaveSelect2Shape( $results );
	}

	public function test_ip_lookup_returns_matching_ip_result() :void {
		TestDataFactory::createIpRecord( '203.0.113.231' );

		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'ip',
			'search'  => '203.0.113',
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$results = (array)( $payload[ 'results' ] ?? [] );
		$this->assertTrue(
			$this->resultsContainId( $results, '203.0.113.231' ),
			'Expected IP lookup results to include the inserted IP.'
		);
		$this->assertResultsHaveSelect2Shape( $results );
	}

	public function test_ip_lookup_returns_matching_numeric_fragment_result() :void {
		TestDataFactory::createIpRecord( '212.159.74.132' );

		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'ip',
			'search'  => '212',
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$results = (array)( $payload[ 'results' ] ?? [] );
		$this->assertTrue(
			$this->resultsContainId( $results, '212.159.74.132' ),
			'Expected numeric IP fragment lookup to include the matching IP.'
		);
		$this->assertResultsHaveSelect2Shape( $results );
	}

	public function test_plugin_lookup_returns_matching_plugin_result() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$search = \strtolower( \substr( $pluginSlug, 0, \max( 2, \min( 12, \strlen( $pluginSlug ) ) ) ) );

		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'plugin',
			'search'  => $search,
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$results = (array)( $payload[ 'results' ] ?? [] );
		$this->assertNotEmpty( $results );
		$this->assertTrue(
			$this->resultsContainId( $results, $pluginSlug ),
			'Expected plugin lookup results to include an installed plugin slug.'
		);
		$this->assertResultsHaveSelect2Shape( $results );
	}

	public function test_theme_lookup_returns_matching_theme_result() :void {
		$themeSlug = $this->firstInstalledThemeSlug();
		$search = \strtolower( \substr( $themeSlug, 0, \max( 2, \min( 12, \strlen( $themeSlug ) ) ) ) );

		$payload = $this->processor()->processAction( InvestigateLookupSelect::SLUG, [
			'subject' => 'theme',
			'search'  => $search,
		] )->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$results = (array)( $payload[ 'results' ] ?? [] );
		$this->assertNotEmpty( $results );
		$this->assertTrue(
			$this->resultsContainId( $results, $themeSlug ),
			'Expected theme lookup results to include an installed theme stylesheet.'
		);
		$this->assertResultsHaveSelect2Shape( $results );
	}

	private function resultsContainId( array $results, string $expectedId ) :bool {
		return $this->findResultById( $results, $expectedId ) !== null;
	}

	private function findResultById( array $results, string $expectedId ) :?array {
		foreach ( $results as $result ) {
			if ( \is_array( $result ) && (string)( $result[ 'id' ] ?? '' ) === $expectedId ) {
				return $result;
			}
		}
		return null;
	}

	private function assertResultsHaveSelect2Shape( array $results ) :void {
		foreach ( $results as $result ) {
			$this->assertIsArray( $result );
			$this->assertArrayHasKey( 'id', $result );
			$this->assertArrayHasKey( 'text', $result );
			$this->assertNotSame( '', (string)( $result[ 'id' ] ?? '' ) );
			$this->assertNotSame( '', (string)( $result[ 'text' ] ?? '' ) );
		}
	}

	private function firstInstalledPluginSlug() :string {
		$plugins = Services::WpPlugins()->getInstalledPluginFiles();
		if ( empty( $plugins ) ) {
			$this->markTestSkipped( 'No installed plugins were available for investigate lookup integration test.' );
		}
		return (string)\array_values( $plugins )[ 0 ];
	}

	private function firstInstalledThemeSlug() :string {
		$themes = Services::WpThemes()->getInstalledStylesheets();
		if ( empty( $themes ) ) {
			$this->markTestSkipped( 'No installed themes were available for investigate lookup integration test.' );
		}
		return (string)\array_values( $themes )[ 0 ];
	}
}
