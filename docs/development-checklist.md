# Shield Security Development Checklist

**For general WordPress plugin development practices, see:** `D:\Work\Dev\AI\Docs\WordPress-Plugin-Development-Checklist.md`

## ⚠️ SHIELD-SPECIFIC PRE-COMMIT REVIEW ⚠️

### 1. Shield Architecture Compliance
- [ ] Verify changes work with Shield's 8-module security system
- [ ] Check for impact on other Shield modules (firewall, scanner, login protection, etc.)
- [ ] Ensure compatibility with Shield's Rules Engine
- [ ] Verify ActionRouter integration for AJAX/REST endpoints

### 2. Shield-Specific Path Handling
- [ ] Check for environment variables specific to Shield (SHIELD_PACKAGE_PATH)
- [ ] Verify paths within Shield's complex directory structure
- [ ] Test with Shield's plugin directory structure (`src/lib/src/Modules/`, etc.)

### 3. Shield Configuration & Testing
- [ ] Run Shield smoke tests: `composer test:smoke` (< 10 seconds)
- [ ] After plugin.json changes: ALWAYS run `composer test:smoke:json` to validate structure
- [ ] Test with Shield's premium feature detection
- [ ] Verify Shield-specific debug logging integration

### 4. Shield-Specific Error Patterns
- [ ] Check for typos in Shield method names (like `3debugPath`)
- [ ] Verify Shield's specific class naming conventions
- [ ] Test Shield module interactions and dependencies

## 🛑 SHIELD FINAL PRE-COMMIT CHECK 🛑

Before typing `git commit`:
1. Review WordPress Plugin Development Checklist (general practices)
2. Review EVERY Shield-specific item above
3. Run smoke tests: `composer test:smoke` (MANDATORY - takes < 10 seconds)
4. Run Shield-specific tests if changes affect plugin.json: `composer test:smoke:json`
5. Test in Shield development environment if possible

## Shield-Specific Development Reminders

### Common Mistakes in Shield Development
- **Case sensitivity**: Creating lowercase directories with uppercase namespaces
- **Path assumptions**: Not checking where files actually are in Shield's structure
- **Version guessing**: Using version numbers without verification
- **No debug output**: Making it hard to diagnose Shield-specific failures
- **Syntax errors**: Simple typos that break Shield's complex architecture
- **Code duplication**: Copying logic instead of leveraging Shield's existing patterns
- **Module interactions**: Not considering how changes affect other Shield modules

### Shield-Specific Verification Steps
1. **Before modifying modules**: Check inter-module dependencies
2. **Before path operations**: Verify paths within Shield's directory structure
3. **Before using Shield tools**: Check version and compatibility with Shield
4. **Before pushing Shield changes**: Test with Shield's test suite
5. **When Shield features fail**: Add Shield-specific debug logging first
6. **Before ANY commit**: Run smoke tests - they validate critical functionality in seconds
7. **After plugin.json changes**: ALWAYS run `composer test:smoke:json` to validate structure

### The Golden Rule for Shield Development
**If uncertain about Shield's architecture or patterns, RESEARCH the existing codebase first, don't ASSUME**

## Quick Reference
- **Main Documentation**: `D:\Work\Dev\AI\Projects\WP_Plugin-Shield\CLAUDE.md`
- **Development Guide**: `D:\Work\Dev\AI\Projects\WP_Plugin-Shield\shield-development-guide.md`
- **Global Standards**: `D:\Work\Dev\AI\Projects\CLAUDE.md`