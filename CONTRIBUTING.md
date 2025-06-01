# Ultimate Push Notifications Development Guidelines

* [Commit Message Standards](#commit-message-standards)

---

## <a name="commit-message-standards"></a> Git Commit Standards

To maintain a clean and understandable project history, we follow strict rules for writing git commit messages. These messages not only enhance readability but are also used to automatically generate the **Ultimate Push Notifications changelog**.

---

### Commit Message Structure

Each commit message should follow this structure:

```
<type>(<scope>): <subject>

<body>

<footer>
```

- The **header** is **mandatory**.
- The **scope** in the header is **optional**.
- Each line should be no longer than **100 characters**, to ensure readability on GitHub and other tools.

---

### Reverting a Commit

To revert a commit:

- Start the message with `revert:` followed by the original commit's header.
- The body should include:  
  `This reverts commit <hash>.`

> You can use the [`git revert`](https://git-scm.com/docs/git-revert) command to generate this automatically.

---

### Commit Types

Use one of the following types to describe the purpose of the commit:

- **New**: Introduce a new feature
- **fix**: Resolve a bug
- **Update**: Update code base
- **docs**: Documentation-only changes
- **style**: Code style changes (e.g., whitespace, formatting, semicolons)
- **refactor**: Code changes that neither fix a bug nor add a feature
- **perf**: Code changes that improve performance
- **test**: Add or update tests
- **chore**: Changes to tooling, build process, or non-code files
- **ci**: CI/CD configuration changes (e.g., GitHub Actions, Travis)
- **build**: Changes to build system or external dependencies (e.g., npm, webpack)

---

### Scope

Use a scope to clarify the area affected, for example:

- `Shipping`
- `Tax`
- `Vendor`

Use `*` if the change impacts multiple areas.

---

### Subject

The subject line should:

- Be written in **imperative, present tense** (e.g., “add”, “fix”, “update”)
- Start with a **lowercase** letter
- **Not** end with a period

Example:
```
fix(tax): correct calculation on inclusive tax
```

---

### Body

In the body, use the imperative present tense.  
Describe:

- What was changed
- Why the change was needed
- How it differs from previous behavior

---

### Footer

Use the footer to:

- Highlight **breaking changes**  
  Start with `BREAKING CHANGE:` followed by an explanation

- Reference GitHub issues using [closing keywords](https://help.github.com/articles/closing-issues-using-keywords/):  
  `Closes #123`

---

For a more detailed explanation, refer to the full [Commit Message Style Guide](https://docs.google.com/document/d/1QrDFcIiPjSLDn3EL15IJygNPiHORgU1_OOAqWjiDU5Y/edit#).