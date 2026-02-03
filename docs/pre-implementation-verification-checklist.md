# Pre-Implementation Verification Guide

## Purpose

This guide helps AI agents plan code changes that integrate correctly into existing systems. It addresses systematic blind spots where AI planning fails: treating code as isolated units rather than parts of a system.

**The Core Problem:** Planning focuses on "what code to write" while missing:
- How the system discovers and registers components
- How configuration drives behavior
- How failures propagate through the system
- How data flows between layers
- How external dependencies actually behave

**The Solution:** Before writing code, answer "how does this fit into the system?" Use tools to gather evidence. Write analysis, not forms.

---

## The Seven Questions

Every implementation plan must answer these seven questions. Each addresses a documented AI planning blind spot.

### 1. Configuration: What configuration changes does this code require?

**The Blind Spot:** Writing implementation code but missing the declarative configuration (schemas, manifests, registries) that makes it work.

**What to investigate:**
- What config files does this project use? (package.json, plugin.json, settings.py, etc.)
- How do similar features declare their configuration?
- What schemas, registrations, or manifests need updating?

**A good answer:** Lists specific configuration files with the exact changes needed. Shows evidence from similar features.

**Red flag:** "The code will work once written" with no configuration mentioned.

---

### 2. Storage: What is the path from physical storage to my code?

**The Blind Spot:** Confusing storage layers (database tables vs ORM vs cache vs API response) and using the wrong access pattern.

**What to investigate:**
- Where does this data physically live?
- What abstraction layers exist above it?
- How do similar features access this data?

**A good answer:** Maps the complete path (e.g., "Database table → Repository class → Service → Controller") with code evidence showing each layer.

**Red flag:** Directly accessing database when an ORM exists, or vice versa.

---

### 3. Registration: How will the system discover this component?

**The Blind Spot:** Creating components that exist in code but the system never finds because they're not registered.

**What to investigate:**
- How does this system discover components? (auto-scanning, explicit registration, config files)
- Where are similar components registered?
- What initialization order or dependencies exist?

**A good answer:** Shows exactly where and how the component will be registered, with evidence from similar components.

**Red flag:** No registration mentioned, or assuming the system "just finds" new code.

---

### 4. Failure: What happens when each external operation fails?

**The Blind Spot:** Planning only the success path. Assuming API calls succeed, database queries return data, and files exist.

**What to investigate:**
- What external operations does this code perform? (API calls, database queries, file I/O)
- How do similar features handle failures?
- What state is left inconsistent if failure occurs mid-operation?

**A good answer:** For each external operation, describes: what can fail, how failure is detected, what the fallback is.

**Red flag:** No error handling mentioned, or generic "handle errors appropriately."

---

### 5. Contracts: What do the external functions actually return?

**The Blind Spot:** Assuming function behavior without verifying. Especially dangerous with functions that return `null|false|error` inconsistently.

**What to investigate:**
- What functions/APIs will you call?
- What do they actually return? (Read the source, don't assume)
- What are the edge cases? (null input, empty result, error conditions)

**A good answer:** Shows actual function signatures from source code. Lists specific return values for success and failure cases.

**Red flag:** "This function returns X" without showing evidence from source.

---

### 6. Data Flow: What happens to null/empty values at each step?

**The Blind Spot:** Logic errors from not tracking data transformations. Null values propagating, type mismatches, invalid intermediate states.

**What to investigate:**
- What transformations does the data go through?
- At each step, what if the input is null? Empty? Malformed?
- What invariants must hold throughout?

**A good answer:** Traces data from input to output, noting type at each step and how edge cases are handled.

**Red flag:** No consideration of null/empty cases, or assuming "valid data" throughout.

---

### 7. Access: Am I using only public APIs?

**The Blind Spot:** Accessing private/internal methods that shouldn't be used directly, leading to fragile code.

**What to investigate:**
- What methods/properties will you access?
- Are they public, protected, or private?
- Is there a public API that provides the same functionality?

**A good answer:** Lists each method accessed with its visibility, verified from source code.

**Red flag:** Accessing methods prefixed with `_`, using reflection, or "reaching into" internal state.

---

## Planning Process

### Step 1: Understand the Landscape

**Goal:** Learn how the existing system works before proposing changes.

**Activities:**

1. **Find similar features.** Search the codebase for features that do something similar. Use semantic search, structural search (same base class), or naming pattern search.

2. **Read the code.** Use tools to actually read similar implementations. Don't rely on memory or training data.

3. **Note the patterns.** How are similar features structured? Registered? Configured? What conventions do they follow?

4. **Check what already exists.** Search for existing utilities, helpers, or capabilities you can reuse instead of writing new code.

**Output:** A narrative explaining how the current system works, citing specific files and code you've read.

**Example output:**
> "User authentication handlers are located in `src/auth/handlers/`. Each extends `BaseAuthHandler` (see `src/auth/BaseAuthHandler.ts:15-45`). They're registered in `src/auth/index.ts` via the `registerHandler()` function. The pattern uses dependency injection through the constructor. I found 3 similar handlers: `PasswordHandler`, `OAuthHandler`, and `TokenHandler`."

---

### Step 2: System Integration Analysis

**Goal:** Determine exactly how your changes integrate with the existing system.

**Address these areas:**

**Configuration:**
- What config files need changes?
- Show the exact configuration you'll add (not just "update config")
- Verify by finding similar configuration entries

**Registration:**
- Where will your component be registered?
- What's the registration code?
- What initialization hooks or timing applies?

**Storage (if applicable):**
- What storage mechanism will you use?
- What's the access pattern?
- How do similar features access the same storage?

**Output:** Specific answers to each relevant area, with code evidence.

**Example output:**
> "Configuration: Add entry to `config/handlers.json` following the pattern at line 23. Registration: Add to the handler map in `src/auth/index.ts:45` using the same `registerHandler()` call as `OAuthHandler`. Storage: Will use the existing `UserRepository` (accessed via `this.repos.users`) following the pattern in `PasswordHandler.ts:67`."

---

### Step 3: Robustness Analysis

**Goal:** Ensure the implementation handles failures and edge cases.

**Address these areas:**

**Failure modes:**
- List each external operation (API call, DB query, file read)
- What can fail?
- How will you detect and handle failure?
- How do similar features handle the same failures?

**Edge cases:**
- What if input is null? Empty? Malformed?
- What happens at each transformation step?
- What invariants must hold?

**Contracts:**
- For each external function, what does it actually return?
- Verify by reading the source or documentation

**Output:** A failure handling plan with evidence from similar features.

**Example output:**
> "The `validateToken()` call can fail with `TokenExpiredError` or `InvalidTokenError` (verified in `src/auth/tokens.ts:89`). Similar handlers catch these explicitly and return a 401 response (see `OAuthHandler.ts:112-125`). I'll follow the same pattern. Edge case: null token input returns early with 400 (matching `PasswordHandler.ts:45`)."

---

### Step 4: Implementation Summary

**Goal:** Create a clear, actionable summary of what to implement.

**The Two-Column Summary:**

| Code to Write | Configuration to Update |
|---------------|------------------------|
| `path/to/new/file.ext` | `config/file.json`: add entry |
| `path/to/modified/file.ext` | `src/registration.ts`: add to map |

**Key decisions:**
- List architectural decisions made and why
- Note any divergence from existing patterns (with justification)

**This summary should be immediately actionable.** Someone reading it should know exactly what files to create/modify and what changes to make.

---

## Quick Reference

### The Seven Questions (Checklist)

Before finalizing any plan, verify you can answer:

- [ ] **Configuration:** What config files change? (Show exact changes)
- [ ] **Storage:** What's the storage access path? (Show each layer)
- [ ] **Registration:** How is this discovered? (Show registration code)
- [ ] **Failure:** What fails and how is it handled? (Show fallback code)
- [ ] **Contracts:** What do external functions return? (Show from source)
- [ ] **Data Flow:** How are null/empty cases handled? (Show at each step)
- [ ] **Access:** Are all methods public? (Show visibility from source)

### Red Flags (Stop and Investigate)

- No configuration changes mentioned for a new feature
- "The system will find it" without showing how
- No error handling for external operations
- Assuming function behavior without reading source
- Accessing private/internal methods
- No similar features found (genuinely novel, or didn't search properly?)

### Evidence Standard

All claims must be backed by evidence gathered in this session:
- **Read files** using `read_file`, `grep`, `codebase_search`, or equivalent
- **Show code snippets** from actual tool output, not memory
- **Cite locations** with file paths and line numbers
- **Don't assume** - if uncertain, investigate

### Common Pitfalls by Question

| Question | Common Pitfall | Fix |
|----------|---------------|-----|
| Configuration | "Just update the config" | Show the exact config entry to add |
| Storage | Using wrong abstraction layer | Map the full path, follow existing patterns |
| Registration | Forgetting to register | Find how similar components register |
| Failure | Only planning success path | For each external op, plan the failure |
| Contracts | Assuming return types | Read the actual function signature |
| Data Flow | Ignoring null cases | Trace null through each transformation |
| Access | Using private methods | Find the public API alternative |

---

## Tiered Depth

**Quick Mode (simple changes):** Answer the 7 questions briefly. Write the two-column summary. ~10-15 minutes.

**Full Mode (complex features):** Write detailed analysis for each step. Use templates from the Appendix if helpful. ~30-45 minutes.

The AI should judge which mode based on:
- Simple: bug fixes, small enhancements, following clear existing patterns
- Complex: new features, architectural changes, no clear existing pattern

---

## Appendix Reference

For detailed analysis templates, enforcement protocols, and framework-specific references, see:

[Pre-Implementation Verification Appendix](pre-implementation-verification-appendix.md)

The appendix contains:
- Detailed analysis templates for complex features
- Evidence and review protocols for verification
- Language/framework-specific registration and visibility patterns
- Concrete examples from the Shield Security plugin
