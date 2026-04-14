# Frontend Architecture Overview

This document outlines the architectural patterns and conventions used in our Nuxt 3 / Vue 3 frontend applications.

---

## 1. Tech Stack

| Layer | Technology |
|---|---|
| Framework | Nuxt 3 |
| Language | TypeScript |
| Styling | Tailwind CSS |
| Testing | Vitest |
| Linting | ESLint + Prettier |
| Package Manager | npm |

---

## 2. Directory Structure

```
├── pages/          # File-based routing (Nuxt auto-routes)
├── components/     # Reusable Vue components
├── composables/    # Reusable composition functions (useXxx)
├── layouts/        # Page layout wrappers
├── middleware/     # Route middleware (auth guards, etc.)
├── plugins/        # Nuxt plugins (registered globally)
├── assets/         # Static assets (images, fonts, global CSS)
├── public/         # Publicly served files (no processing)
├── data/           # Static data / mock data
└── nuxt.config.ts  # Nuxt configuration
```

---

## 3. Development Commands (Makefile)

| Command | Purpose |
|---|---|
| `make local` | Start dev server (`npm run dev`) |
| `make build` | Production build (`npm run build`) |
| `make deps` | Install dependencies (`npm install`) |

### npm scripts

| Script | Purpose |
|---|---|
| `npm run dev` | Start development server |
| `npm run build` | Build for production |
| `npm run lint` | Run ESLint |
| `npm run test` | Run Vitest unit tests |

---

## 4. Component Conventions

- Use `<script setup lang="ts">` (Composition API) — never Options API.
- Components are PascalCase filenames: `UserCard.vue`, `InvoiceTable.vue`.
- Props must be typed with TypeScript interfaces, not inline objects.
- Emit names are kebab-case: `emit('update:modelValue')`, `emit('item-selected')`.

```vue
<script setup lang="ts">
interface Props {
  userId: string
  isActive?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isActive: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()
</script>
```

---

## 5. Composables

- Filename and function name are both prefixed with `use`: `useAuth.ts` → `useAuth()`.
- Composables handle API calls, shared state, and reusable logic.
- Always return reactive refs or computed properties — never raw values.

```ts
// composables/useUser.ts
export const useUser = () => {
  const user = ref<User | null>(null)

  const fetchUser = async (id: string) => {
    user.value = await $fetch(`/api/users/${id}`)
  }

  return { user: readonly(user), fetchUser }
}
```

---

## 6. API Integration

- Use Nuxt's `$fetch` or `useFetch` — never raw `fetch` or `axios`.
- API base URL is configured via runtime config in `nuxt.config.ts`.
- All API calls live in composables, never directly in `<script setup>`.

```ts
// In a composable
const { data, error } = await useFetch('/api/invoices', {
  method: 'POST',
  body: payload,
})
```

---

## 7. TypeScript Standards

- Strict mode is enabled — no `any` unless absolutely necessary with a comment explaining why.
- Interfaces over `type` for object shapes.
- All async functions must have explicit return type annotations.
- No implicit `undefined` — use optional chaining (`?.`) and nullish coalescing (`??`).

---

## 8. Routing & Middleware

- Pages use file-based routing (`pages/` directory).
- Route middleware is defined in `middleware/` and applied via `definePageMeta`.
- Auth guard example:

```ts
// middleware/auth.ts
export default defineNuxtRouteMiddleware(() => {
  const { isAuthenticated } = useAuth()
  if (!isAuthenticated.value) {
    return navigateTo('/login')
  }
})
```

---

## 9. Testing Standards

- **Framework**: Vitest with `@nuxt/test-utils`.
- Test files live next to the component/composable: `UserCard.test.ts`.
- Use `mountSuspended` from `@nuxt/test-utils` for component tests.

```ts
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, it, expect } from 'vitest'
import UserCard from './UserCard.vue'

describe('UserCard', () => {
  it('renders user name', async () => {
    const wrapper = await mountSuspended(UserCard, {
      props: { userId: '123', name: 'Alice' },
    })
    expect(wrapper.text()).toContain('Alice')
  })
})
```

---

## 10. Linting & Code Style

- ESLint is enforced in CI — all PRs must pass lint.
- Prettier handles formatting — do not manually format; let Prettier do it.
- Max line length: **100 characters**.
- No unused variables or imports (enforced by ESLint).

```bash
npm run lint       # check
npm run lint --fix # auto-fix
```
