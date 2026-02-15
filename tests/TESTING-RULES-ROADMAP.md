# Rules/Firewall Testing Roadmap

## Next Action
- `Action`: Run the new critical-path integration tests in Docker and capture baseline results/issues.
- `Owner`: Engineering
- `Status`: Ready
- `Pointer`: `composer test:fast` then inspect `tests/Integration/Rules/`

## Default Test Commands
- `composer test`  
  Full test suite (unit + integration).
- `composer test:fast`  
  Critical subset focused on rules, firewall, rate-limit behavior.

## Progress Board
| Phase | Scope | Status | Next Step |
|---|---|---|---|
| 0 | Workflow simplification + tracking doc | Done | Keep command docs current |
| 1 | Integration determinism/caches | Done | Monitor for additional shared static state |
| 2 | Rules pipeline behavior | In Progress | Expand controller/rebuild edge-case coverage |
| 3 | Rules builder/manager persistence | In Progress | Add more negative-path assertions |
| 4 | Firewall + rate-limit deep behavior | In Progress | Validate across more payload/path edge cases |
| 5 | Property-style deterministic fuzz | Planned | Add bounded, seeded cases after baseline stabilization |

## Coverage Intent Map
| Target | Intent | Test Location |
|---|---|---|
| `src/ActionRouter/Actions/RuleBuilderAction.php` | Create/reset persistence and sanitization | `tests/Integration/Rules/RuleBuilderActionIntegrationTest.php` |
| `src/ActionRouter/Actions/RulesManagerTableAction.php` | Activate/deactivate/delete/reorder/export actions | `tests/Integration/Rules/RulesManagerTableActionIntegrationTest.php` |
| `src/Rules/Conditions/RequestTriggersFirewall.php` | Request matching behavior | `tests/Integration/Rules/FirewallRuleBehaviorTest.php` |
| `src/Rules/Conditions/FirewallPatternFoundInRequest.php` | Exclusions and trigger metadata | `tests/Integration/Rules/FirewallRuleBehaviorTest.php` |
| `src/Rules/Build/Core/IsRateLimitExceeded.php` | Threshold + logged-in behavior | `tests/Integration/Rules/RateLimitRuleBehaviorTest.php` |
| `src/Rules/Conditions/IsRateLimitExceeded.php` | DB-backed request counting metadata | `tests/Integration/Rules/RateLimitRuleBehaviorTest.php` |
| `tests/Integration/ShieldIntegrationTestCase.php` | Deterministic cache resets | `tests/Integration/InfrastructureSmokeTest.php` |

## Known Blockers / Risks
- Local integration tests need WordPress test env or Docker parity.
- Bash/Docker invocation may fail in some local shells; treat as environment issue, not test logic issue.

## Deferred Option (Explicitly Deferred)
- Separate split-suite commands (for example per-area unit/integration commands) are deferred.
- Revisit only if:
  1. `composer test:fast` is no longer fast enough, or
  2. CI/runtime pressure requires finer partitioning, or
  3. Failure triage needs narrower suite boundaries.
