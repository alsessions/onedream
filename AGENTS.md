# Project Overview

This is a Craft CMS 5 website.

## Stack

- Craft CMS 5
- Twig
- Tailwind CSS
- Vanilla JavaScript
- Alpine.js where appropriate
- DDEV for local development
- Apache/PHP on production
- GitHub for source control

## Development Preferences

- Prefer simple solutions over abstractions.
- Use Twig idiomatically.
- Use Tailwind utility classes directly in templates.
- Do not introduce React, Vue, TypeScript, or build frameworks unless specifically requested.
- Prefer vanilla JavaScript before adding another dependency.
- Use Alpine.js for small interactive components.

## Craft CMS

- Assume Craft 5 syntax.
- Prefer Craft element queries over custom PHP.
- Respect existing section, field, and asset handles.
- Do not rename Craft fields or handles unless explicitly requested.
- Do not edit generated project config manually without explicit permission.

## Tailwind

- Use responsive mobile-first classes.
- Keep grids compact.
- Avoid custom CSS when Tailwind utilities can accomplish the same thing.
- Preserve existing Tailwind conventions in the project.

## Git

- Do not commit or push unless explicitly asked.
- Do not rewrite Git history.
- Do not modify `.env`.
- Before destructive Git operations, explain what will be affected.

## Working Style

- Inspect existing code before implementing changes.
- Follow patterns already used elsewhere in the project.
- Keep changes narrowly scoped to the requested task.
- Avoid unrelated cleanup or refactoring.