<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\BlockAuthorFishing;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\DisableFileEditing as DisableFileEditingRuleBuilder,
	Build\Core\IsRequestAuthorDiscovery as AuthorDiscoveryRuleBuilder,
	Processors\ProcessConditions,
	Processors\ResponseProcessor,
	Responses,
	RuleVO,
	RulesController
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class FirewallCoreRulesBehaviorTest extends ShieldIntegrationTestCase {

	private bool $userHasCapFilterExisted = false;

	private $userHasCapFilterSnapshot = null;

	public function set_up() {
		parent::set_up();

		$this->snapshotUserHasCapFilter();
		\wp_set_current_user( 0 );
		$this->resetRuleRequestState();
		$this->setCoreFirewallOptions();
	}

	public function tear_down() {
		$this->restoreUserHasCapFilter();
		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	private function snapshotUserHasCapFilter() :void {
		$wpFilters = $GLOBALS[ 'wp_filter' ] ?? [];
		$this->userHasCapFilterExisted = \array_key_exists( 'user_has_cap', \is_array( $wpFilters ) ? $wpFilters : [] );
		$this->userHasCapFilterSnapshot = $this->userHasCapFilterExisted
			? $this->cloneFilterSnapshot( $GLOBALS[ 'wp_filter' ][ 'user_has_cap' ] )
			: null;
	}

	private function cloneFilterSnapshot( $filter ) {
		return \is_object( $filter ) ? clone $filter : $filter;
	}

	private function restoreUserHasCapFilter() :void {
		if ( $this->userHasCapFilterExisted ) {
			$GLOBALS[ 'wp_filter' ][ 'user_has_cap' ] = $this->userHasCapFilterSnapshot;
		}
		else {
			unset( $GLOBALS[ 'wp_filter' ][ 'user_has_cap' ] );
		}
	}

	private function resetRuleRequestState( string $path = '/', array $query = [], array $post = [] ) :void {
		$this->resetIpCaches();

		$con = $this->requireController();
		$con->rules = new RulesController();
		$con->rules->setThisRequest( $con->this_req )->execute();

		$con->this_req->request_bypasses_all_restrictions = false;
		$con->this_req->request_subject_to_shield_restrictions = true;
		$con->this_req->path = $path;
		$con->this_req->request->query = $query;
		$con->this_req->request->post = $post;
	}

	private function setCoreFirewallOptions( array $overrides = [] ) :void {
		$options = \array_merge( [
			'disable_file_editing'   => 'N',
			'block_author_discovery' => 'N',
		], $overrides );

		$opts = $this->requireController()->opts;
		foreach ( $options as $key => $value ) {
			$opts->optSet( $key, $value );
		}
	}

	private function runRuleConditions( RuleVO $rule ) :bool {
		return ( new ProcessConditions( $rule->conditions ) )
			->setThisRequest( $this->requireController()->this_req )
			->process();
	}

	private function responseDefinition( array $responses, string $responseClass ) :array {
		$response = \current( \array_filter(
			$responses,
			static fn( array $resp ) :bool => ( $resp[ 'response' ] ?? '' ) === $responseClass
		) );

		$this->assertNotEmpty( $response, 'Rule must define response: '.$responseClass );
		return $response;
	}

	public function test_disable_file_editing_rule_matches_enabled_non_bypassed_request() :void {
		$this->setCoreFirewallOptions( [
			'disable_file_editing' => 'Y',
		] );
		$this->resetRuleRequestState();

		$rule = ( new DisableFileEditingRuleBuilder() )->build();

		$this->assertSame( DisableFileEditingRuleBuilder::SLUG, $rule->slug );
		$this->assertTrue( $this->runRuleConditions( $rule ) );
		$this->responseDefinition( $rule->responses, Responses\DisableFileEditing::class );

		$defineResponse = $this->responseDefinition( $rule->responses, Responses\PhpSetDefine::class );
		$this->assertSame( 'DISALLOW_FILE_EDIT', $defineResponse[ 'params' ][ 'name' ] ?? '' );
		$this->assertTrue( $defineResponse[ 'params' ][ 'value' ] ?? false );
	}

	public function test_disable_file_editing_rule_does_not_match_when_disabled() :void {
		$this->setCoreFirewallOptions( [
			'disable_file_editing' => 'N',
		] );
		$this->resetRuleRequestState();

		$this->assertFalse( $this->runRuleConditions( ( new DisableFileEditingRuleBuilder() )->build() ) );
	}

	public function test_disable_file_editing_rule_respects_request_bypass() :void {
		$this->setCoreFirewallOptions( [
			'disable_file_editing' => 'Y',
		] );
		$this->resetRuleRequestState();
		$this->requireController()->this_req->request_bypasses_all_restrictions = true;

		$this->assertFalse( $this->runRuleConditions( ( new DisableFileEditingRuleBuilder() )->build() ) );
	}

	public function test_disable_file_editing_response_removes_wordpress_file_editing_capabilities() :void {
		$disallowFileEditDefinedBefore = \defined( 'DISALLOW_FILE_EDIT' );

		( new Responses\DisableFileEditing() )
			->setThisRequest( $this->requireController()->this_req )
			->execResponse();

		foreach ( [ 'edit_themes', 'edit_plugins', 'edit_files' ] as $capability ) {
			$filteredCaps = \apply_filters( 'user_has_cap', [ $capability => true ], [], [ $capability ], null );
			$this->assertFalse( $filteredCaps[ $capability ] ?? true, $capability );
		}

		$unrelatedCaps = \apply_filters( 'user_has_cap', [ 'manage_options' => true ], [], [ 'manage_options' ], null );
		$this->assertTrue( $unrelatedCaps[ 'manage_options' ] ?? false );
		$this->assertSame( $disallowFileEditDefinedBefore, \defined( 'DISALLOW_FILE_EDIT' ) );
	}

	public function test_author_discovery_rule_matches_anonymous_numeric_author_request() :void {
		$this->setCoreFirewallOptions( [
			'block_author_discovery' => 'Y',
		] );
		$this->resetRuleRequestState( '/', [
			'author' => '1',
		] );

		$rule = ( new AuthorDiscoveryRuleBuilder() )->build();

		$this->assertSame( AuthorDiscoveryRuleBuilder::SLUG, $rule->slug );
		$this->assertTrue( $this->runRuleConditions( $rule ) );

		$meta = $this->requireController()->rules->getConditionMeta()->getRawData();
		$this->assertSame( 'author', $meta[ 'match_request_param' ] ?? '' );
		$this->assertSame( '1', $meta[ 'match_request_value' ] ?? '' );

		$eventResponse = $this->responseDefinition( $rule->responses, Responses\EventFire::class );
		$this->assertSame( 'block_author_fishing', $eventResponse[ 'params' ][ 'event' ] ?? '' );

		$displayResponse = $this->responseDefinition( $rule->responses, Responses\DisplayBlockPage::class );
		$this->assertSame( BlockAuthorFishing::SLUG, $displayResponse[ 'params' ][ 'block_page_slug' ] ?? '' );

		$this->captureShieldEvents();
		$eventOnlyRule = ( new RuleVO() )->applyFromArray( [
			'slug'                    => 'test_author_discovery_event_contract',
			'name'                    => 'Test Author Discovery Event Contract',
			'conditions'              => fn() => true,
			'responses'               => [ $eventResponse ],
			'immediate_exec_response' => true,
		] );
		( new ResponseProcessor( $eventOnlyRule ) )
			->setThisRequest( $this->requireController()->this_req )
			->run();

		$this->assertCount( 1, $this->getCapturedEventsByKey( 'block_author_fishing' ) );
	}

	public function test_author_discovery_rule_does_not_match_when_disabled() :void {
		$this->setCoreFirewallOptions( [
			'block_author_discovery' => 'N',
		] );
		$this->resetRuleRequestState( '/', [
			'author' => '1',
		] );

		$this->assertFalse( $this->runRuleConditions( ( new AuthorDiscoveryRuleBuilder() )->build() ) );
	}

	public function test_author_discovery_rule_does_not_match_logged_in_user() :void {
		$this->setCoreFirewallOptions( [
			'block_author_discovery' => 'Y',
		] );
		$this->loginAsAdministrator();
		$this->resetRuleRequestState( '/', [
			'author' => '1',
		] );

		$this->assertFalse( $this->runRuleConditions( ( new AuthorDiscoveryRuleBuilder() )->build() ) );
	}

	public function test_author_discovery_rule_respects_request_bypass() :void {
		$this->setCoreFirewallOptions( [
			'block_author_discovery' => 'Y',
		] );
		$this->resetRuleRequestState( '/', [
			'author' => '1',
		] );
		$this->requireController()->this_req->request_bypasses_all_restrictions = true;

		$this->assertFalse( $this->runRuleConditions( ( new AuthorDiscoveryRuleBuilder() )->build() ) );
	}

	public function test_author_discovery_rule_ignores_non_numeric_author_request() :void {
		$this->setCoreFirewallOptions( [
			'block_author_discovery' => 'Y',
		] );
		$this->resetRuleRequestState( '/', [
			'author' => 'abc',
		] );

		$this->assertFalse( $this->runRuleConditions( ( new AuthorDiscoveryRuleBuilder() )->build() ) );
	}
}
