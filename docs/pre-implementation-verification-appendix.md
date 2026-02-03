# Pre-Implementation Verification Appendix

This appendix supports the [Pre-Implementation Verification Guide](pre-implementation-verification-checklist.md). Use it for:
- Detailed analysis templates (complex features)
- Evidence and review protocols (verification)
- Language/framework references (adaptation)
- Concrete examples (Shield Security plugin)

---

## 1. Detailed Analysis Templates

Use these templates for complex features that benefit from structured breakdown.

### 1.1 Pattern Recognition Template

Use when documenting similar features found in the codebase.

**Similar Implementation Analysis:**

| Feature | Location | Key Pattern |
|---------|----------|-------------|
| Feature 1 | `path/to/file.ext:lines` | Brief description |
| Feature 2 | `path/to/file.ext:lines` | Brief description |
| Feature 3 | `path/to/file.ext:lines` | Brief description |

**Pattern Comparison:**

| Element | Existing Pattern | My Implementation | Match? |
|---------|------------------|-------------------|--------|
| File location | `path/pattern/` | `my/path/` | ✓/✗ |
| Base class | `extends X` | `extends X` | ✓/✗ |
| Registration | How registered | How I'll register | ✓/✗ |
| Naming | Convention used | My naming | ✓/✗ |

**If diverging from pattern, justify:** _______________

---

### 1.2 Configuration Audit Template

Use when mapping all configuration changes required.

**Configuration Files in This Project:**

| Type | File | Purpose |
|------|------|---------|
| Package manifest | | |
| App config | | |
| Schema/migrations | | |
| Registration | | |

**My Configuration Changes:**

| File | Section | Change | Exact Content |
|------|---------|--------|---------------|
| | | Add/Modify | ```content``` |

---

### 1.3 Storage Layer Map Template

Use when documenting data access paths.

| Layer | Component | Access Pattern | Code Reference |
|-------|-----------|----------------|----------------|
| Physical | Table/File/API | Direct access | `file:line` |
| ORM/Abstraction | Model/Repository | `model.find()` | `file:line` |
| Service | Service class | `service.get()` | `file:line` |
| Interface | API endpoint | `GET /path` | `file:line` |

---

### 1.4 Failure Mode Matrix Template

Use when documenting failure handling for each external operation.

| Operation | Fails With | Detection | Fallback | Similar Feature |
|-----------|-----------|-----------|----------|-----------------|
| API call | Timeout, 4xx, 5xx | `isError()` | Return default | `file:line` |
| DB query | Connection, not found | `catch` | Log, rethrow | `file:line` |
| File read | Not found, permission | `exists()` check | Create default | `file:line` |

**Partial Success Handling:**

| Operation | Can Partially Succeed? | Inconsistent State | Recovery |
|-----------|------------------------|-------------------|----------|
| | Yes/No | Description | How to recover |

---

### 1.5 Data Flow Trace Template

Use when documenting data transformations.

| Step | Operation | Input Type | Output Type | Null Handling |
|------|-----------|------------|-------------|---------------|
| 1 | Receive input | `T \| null` | Validated `T` | Return 400 if null |
| 2 | Transform | `T` | `U` | N/A (validated) |
| 3 | Store | `U` | `void` | N/A |

**Edge Cases:**

| Condition | What Happens | Code |
|-----------|--------------|------|
| Input null | | |
| Input empty | | |
| Invalid format | | |

---

### 1.6 External Contract Verification Template

Use when documenting external function behavior.

| Function | Source | Success Return | Failure Return | My Handling |
|----------|--------|----------------|----------------|-------------|
| `func()` | `file:line` | `Type` | `null/false/Error` | Check before use |

---

### 1.7 Reuse Audit Template

Use when checking for existing functionality.

| Capability Needed | Search Terms | Found? | Location | Decision |
|-------------------|--------------|--------|----------|----------|
| | | Yes/No | `file` | Reuse/Write new |

---

## 2. Evidence and Review Protocol

### 2.1 Evidence Requirements

**All claims must be backed by evidence gathered during this planning session.**

**Valid evidence:**
- Code snippets from `read_file` tool output
- Search results from `grep` or `codebase_search`
- File paths with line numbers you've actually read

**Invalid evidence:**
- Code from memory or training data
- Assumptions without verification
- Paraphrased code instead of actual quotes

**Citation format:**
```
File: `path/to/file.ext:start-end`
Tool: read_file / grep / codebase_search

```code
// Actual code from tool output
```
```

### 2.2 Tool Call Log

When doing detailed analysis, maintain a log of tool usage:

| # | Tool | Target | Purpose | Finding |
|---|------|--------|---------|---------|
| 1 | `read_file` | `path/to/file` | Find registration | Found `register()` at line 45 |
| 2 | `grep` | `"pattern"` | Find similar | 3 matches |

**Minimum coverage for complex features:**
- 3+ file reads for pattern recognition
- 1+ search for existing capabilities
- 2+ reads for configuration patterns
- 2+ reads for registration patterns
- 1+ read for failure handling patterns

### 2.3 Review Protocol

**For reviewers validating a completed plan:**

**Quick Check (2 min):**
- [ ] Two-column summary exists with actual file paths
- [ ] All 7 questions addressed
- [ ] No placeholders ("TODO", "...", "TBD")

**Spot Check (5 min):**
Pick 3 file citations and verify:
- File exists
- Code snippet matches actual file content
- Line numbers are accurate

**Deep Check (for critical features):**
- [ ] Pattern comparison shows actual code from codebase
- [ ] Configuration changes show exact content
- [ ] Failure handling matches patterns from similar features
- [ ] No contradictions between sections

**Rejection criteria:**
- Citations don't match actual files
- Code snippets appear fabricated
- Critical questions unanswered
- No evidence of tool usage

**Acceptance criteria:**
- Spot checks pass
- All 7 questions answered with evidence
- Two-column summary is actionable

---

## 3. Language/Framework Reference

### 3.1 Registration Mechanisms by Framework

| Framework | Registration Mechanism | Where to Look |
|-----------|----------------------|---------------|
| WordPress | `add_action`/`add_filter`, plugin headers | Plugin files, `functions.php` |
| Django | `INSTALLED_APPS`, URL patterns, signals | `settings.py`, `urls.py` |
| Express.js | `app.use()`, route files | `app.js`, `routes/` |
| Spring | `@Component`, `@Bean`, XML config | Annotated classes, `application.properties` |
| Rails | `config/routes.rb`, initializers | `config/` directory |
| Go (wire) | Provider functions, wire sets | `wire.go` files |
| Rust | `mod` declarations, `Cargo.toml` features | `lib.rs`, `main.rs` |
| React | Component imports, route definitions | `App.tsx`, router config |
| Vue | Component registration, Vue.use() | `main.ts`, plugin files |
| NestJS | `@Module` decorators, providers array | Module files |

### 3.2 Visibility/Access Control by Language

| Language | Public | Package/Internal | Protected | Private |
|----------|--------|------------------|-----------|---------|
| PHP | `public` | N/A | `protected` | `private` |
| Python | No prefix | `_prefix` | `_prefix` | `__prefix` |
| JavaScript | `export` | Non-exported | N/A | `#field` |
| TypeScript | `public` | `internal` | `protected` | `private` |
| Go | `Capitalized` | `lowercase` | N/A | N/A |
| Rust | `pub` | `pub(crate)` | `pub(super)` | Default |
| Java | `public` | Default (package) | `protected` | `private` |
| C# | `public` | `internal` | `protected` | `private` |
| Kotlin | `public` | `internal` | `protected` | `private` |

### 3.3 Configuration Files by Ecosystem

| Ecosystem | Package Manifest | App Config | Schema/Migration |
|-----------|-----------------|------------|------------------|
| PHP/Composer | `composer.json` | Various | Doctrine migrations |
| Node.js | `package.json` | `.env`, config files | Prisma, Knex |
| Python | `pyproject.toml` | `settings.py`, `.env` | Alembic |
| Go | `go.mod` | YAML/TOML config | SQL migrations |
| Rust | `Cargo.toml` | Various | Diesel |
| Java/Maven | `pom.xml` | `application.properties` | Flyway |
| .NET | `*.csproj` | `appsettings.json` | EF migrations |
| Ruby/Rails | `Gemfile` | `config/*.yml` | ActiveRecord |

### 3.4 Common Function Contract Gotchas

**Functions that return inconsistent types on failure:**

| Language | Pattern | Gotcha | Safe Check |
|----------|---------|--------|------------|
| PHP | `strpos()` | Returns `false` OR `0` | Use `=== false` |
| PHP | `get_option()` | Returns `false` OR stored value | Check with default |
| Python | `dict.get()` | Returns `None` OR stored value | Provide default |
| JavaScript | `array.find()` | Returns `undefined` OR element | Explicit check |
| JavaScript | `JSON.parse()` | Throws on invalid | Wrap in try/catch |
| Go | Multiple returns | Error as second return | Always check `err` |

---

## 4. Example: Shield Security Plugin (PHP/WordPress)

This section shows concrete examples from the Shield Security WordPress plugin.

### 4.1 Shield Configuration Files

| File | Purpose |
|------|---------|
| `plugin.json` | Option schemas, module definitions |
| `src/lib/src/Modules/*/` | Module-specific configurations |
| `webpack.config.js` | Asset bundling |
| `composer.json` | Dependencies (root and `src/lib/`) |

### 4.2 Shield Registration Patterns

**Hook registration:**
```php
add_action( 'init', [ $this, 'onWpInit' ] );
add_filter( 'shield/collate_rules', [ $this, 'addRules' ] );
```

**Module handler registration:**
```php
protected function enumHandlers() :array {
    return [
        'handler_key' => HandlerClass::class,
    ];
}
```

**Finding registration patterns:**
```bash
# Find module registrations
grep -rn "enumHandlers\|getHandlerClasses" src/lib/src/ --include="*.php"

# Find hook registrations
grep -rn "add_action\|add_filter" src/lib/src/Modules/ --include="*.php"

# Find configuration entries
grep -A 5 "feature_name" plugin.json
```

### 4.3 Shield Storage Layers

| Layer | Component | Access Pattern |
|-------|-----------|----------------|
| Physical | `wp_options` table | Direct WordPress API |
| Physical | Custom tables (`wp_shield_*`) | Schema in plugin |
| Abstraction | `ModOptions` class | `$mod->opts()->get()` |
| Cache | Transients | `get_transient()` |

### 4.4 Shield Common Traits

| Trait | Purpose | Usage |
|-------|---------|-------|
| `PluginControllerConsumer` | Access plugin controller | `$this->con()` |
| Module-specific consumers | Access module services | `$this->mod()` |

### 4.5 WordPress Function Contracts

| Function | Success Return | Failure Return | Check Pattern |
|----------|----------------|----------------|---------------|
| `get_user_by()` | `WP_User` | `false` | `if ($user === false)` |
| `get_option()` | `mixed` | `false` or default | `if ($opt === false)` |
| `wp_remote_get()` | `array` | `WP_Error` | `if (is_wp_error($resp))` |
| `get_post()` | `WP_Post` | `null` | `if ($post === null)` |
| `get_term()` | `WP_Term` | `WP_Error\|null\|false` | `if (!$term instanceof WP_Term)` |

### 4.6 Example Analysis for Shield

**Scenario:** Adding a new security handler

**Question 1 - Configuration:**
> Configuration lives in `plugin.json`. Similar handlers define options in the `properties.options` section. I'll add my option definition there following the pattern at `plugin.json:245`.

**Question 2 - Storage:**
> Options are stored in `wp_options` via the `ModOptions` abstraction. Access pattern: `$this->opts()->get('option_name')`. Verified in `src/lib/src/Modules/Base/Options.php`.

**Question 3 - Registration:**
> Handlers are registered via `enumHandlers()` in the module class. Example from `HackGuard` module at `src/lib/src/Modules/HackGuard/ModCon.php:67`. I'll add `'my_handler' => MyHandler::class` to the return array.

**Question 4 - Failure:**
> Similar handlers check API responses with `is_wp_error()`. Fallback is to log and continue without blocking. Pattern from `src/lib/src/Modules/IPs/Lib/Bots/NotBot/NotBotHandler.php:89`.

**Question 5 - Contracts:**
> Using `wp_remote_get()` which returns `array|WP_Error`. Must check with `is_wp_error()` before accessing response body.

**Question 6 - Data Flow:**
> Input: request data (can be null). Step 1: Validate presence. Step 2: Sanitize. Step 3: Process. Null input returns early with no action. Pattern from similar handlers.

**Question 7 - Access:**
> Using `$this->opts()` (protected, accessible in subclass), `$this->con()` (from trait, public access). All methods I'm calling are public.

**Two-Column Summary:**

| Code to Write | Configuration to Update |
|---------------|------------------------|
| `src/lib/src/Modules/HackGuard/Lib/MyHandler.php` | `plugin.json`: add option schema |
| | `ModCon.php`: add to `enumHandlers()` |

---

## 5. Cross-Platform Verification Commands

### Finding Similar Features

```bash
# Unix/Mac
grep -rn "pattern" src/ --include="*.ext"
find . -name "*.ext" -exec grep -l "pattern" {} \;

# Windows PowerShell
Select-String -Pattern "pattern" -Path src\* -Recurse -Include *.ext
Get-ChildItem -Recurse -Filter *.ext | Select-String "pattern"

# Cross-platform (ripgrep)
rg "pattern" src/ -t ext
rg "pattern" --type-add 'custom:*.ext' -t custom
```

### Verifying File Contents

```bash
# Unix/Mac
sed -n '10,20p' file.ext          # Show lines 10-20
head -n 50 file.ext               # First 50 lines
tail -n 50 file.ext               # Last 50 lines

# Windows PowerShell
Get-Content file.ext | Select-Object -Skip 9 -First 11  # Lines 10-20
Get-Content file.ext -Head 50     # First 50 lines
Get-Content file.ext -Tail 50     # Last 50 lines
```

### Finding Registration Points

```bash
# Find class instantiation
rg "new ClassName" src/

# Find function calls
rg "registerHandler\(" src/

# Find configuration entries
rg -A 3 '"feature_name"' config/
```

---

## 6. When No Similar Feature Exists

If searches return no similar features, document:

1. **Search terms used:** What did you search for?
2. **Why nothing was found:** Is this genuinely novel, or different domain?
3. **Closest analogy:** What's the most similar thing, even if different?
4. **Architectural pattern:** What general pattern applies? (Repository, Factory, Observer, etc.)
5. **New pattern documentation:** Future similar features should follow this approach.

This situation is rare. Usually, similar features exist but weren't found due to:
- Wrong search terms
- Different naming conventions
- Feature in a different module/package

When truly novel, extra care is needed as there's no pattern to follow.

