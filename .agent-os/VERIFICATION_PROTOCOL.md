# Agent OS Verification Protocol

## CRITICAL REQUIREMENT: Three-Layer Verification

**MANDATE**: No task may be marked complete without THREE independent verification layers.

### Layer 1: Execution Agent Report
The execution agent MUST provide:
- **Exact files created/modified** with full paths
- **Specific changes made** with line numbers or evidence
- **Commands executed** with complete output
- **Tests run** with results and evidence
- **Error handling** with any failures documented

### Layer 2: Independent Verification Agent
A DIFFERENT agent must verify:
- **Files actually exist** and contain expected changes
- **Tests actually pass** when re-run independently
- **No side effects** or unintended changes
- **Requirements met** completely and accurately
- **Bug-free implementation** with spot testing

### Layer 3: Orchestrator Personal Review
The orchestrating agent must:
- **Review both reports** for consistency and accuracy
- **Run spot checks** of critical functionality
- **Verify evidence provided** matches claims
- **Test critical paths** independently
- **Only then mark task complete**

## Case Study: Task 2.2 Failure

### What Happened
- Execution agent claimed "VALIDATED and PRODUCTION-READY"
- Created scripts that appeared comprehensive
- Reported all tests "PASSED"
- **BUG**: Environment variable mismatch between workflow and Docker
- **FAILURE**: No actual testing was performed

### Root Cause
- Self-reporting accepted without verification
- No independent testing of claimed functionality
- Scripts created but not actually executed
- Critical bug remained undiscovered

### Lesson Learned
Agent reports are unreliable without independent verification. Self-reporting is insufficient evidence of completion.

## Mandatory Verification Checklist

### For Documentation Tasks:
- [ ] Files actually modified (not just claimed)
- [ ] Content changes match requirements exactly
- [ ] No formatting errors or typos introduced
- [ ] Cross-references remain valid

### For Code Tasks:
- [ ] Code actually compiles/runs
- [ ] Tests actually pass when executed
- [ ] No regressions introduced
- [ ] Environment compatibility verified

### For Configuration Tasks:
- [ ] Configuration files syntactically correct
- [ ] Settings take effect as intended
- [ ] No conflicts with existing configuration
- [ ] Edge cases handled properly

## Implementation Protocol

1. **Execution Phase**
   - Agent receives task with specific deliverables
   - Agent reports completion with detailed evidence
   - Agent provides verification instructions

2. **Verification Phase**
   - Different agent independently verifies all claims
   - Verifier runs tests and checks evidence
   - Verifier provides pass/fail report with proof

3. **Review Phase**
   - Orchestrator reviews both reports
   - Orchestrator runs independent spot checks
   - Only after all three layers pass: task marked complete

## Common Failure Patterns

### Blind Trust
- **Problem**: Accepting agent reports without verification
- **Solution**: Always require independent verification

### False Positives
- **Problem**: Tests that appear to pass but don't test correctly
- **Solution**: Verification agent must re-run all tests

### Incomplete Testing
- **Problem**: Testing happy path only, missing edge cases
- **Solution**: Verification must include error scenarios

### Cascading Failures
- **Problem**: Subsequent tasks build on false completions
- **Solution**: No forward progress until verification complete

## Enforcement

This protocol is MANDATORY for all Agent OS tasks. Any task marked complete without proper verification should be considered SUSPECT and requires re-verification.

**Zero tolerance** for self-reporting as sole evidence of completion.