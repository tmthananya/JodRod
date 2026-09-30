# AGENTS.md

## Development Rules

1. BRIEF.md is the source of truth.
2. Work on one feature at a time.
3. Do not implement future features early.
4. Do not rebuild the project from scratch.
5. Preserve working existing functionality.
6. Do not change the framework.
7. Do not install unnecessary packages.
8. Explain why a new package is required before installing it.
9. Keep the application focused on flood parking.
10. Use CRUD as the core application pattern.
11. Data should be stored in the database rather than hard-coded into UI components.
12. Do not create fake permanent data.
13. Do not add fields that are not required by the current feature.
14. Keep database logic separate from presentation where practical.
15. Use environment variables for database configuration.
16. Never expose private database credentials in frontend code.
17. Test every feature before moving to the next step.
18. After completing a step, create a git save point.
19. Do not claim a feature works without testing it.
20. Do not implement unrelated features.

---

## Workflow for Every Implementation Step

For every implementation step:

1. **Read BRIEF.md first**: Verify alignment with project goals, constraints, and current scope.
2. **Explain the files that will change**: List and explain the files to be modified, added, or removed.
3. **Make only the requested changes**: Restrict changes strictly to the current feature or fix.
4. **Run the application**: Ensure the local environment runs without syntax or runtime errors.
5. **Test the requested functionality**: Execute functional and API verification to confirm it works as expected.
6. **Report the result**: Provide clear, factual evidence of testing and verification results.
7. **Create a git commit**: Commit the changes as a verifiable save point.
