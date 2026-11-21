# NNR Co. IT Service Request Tracker Frontend

Next.js 16 application with TypeScript and the App Router for the NNR Co. IT Service Request Tracker. It talks to the backend API with session cookies and never stores tokens in the browser.

The [root README](../README.md) is the full guide. It covers prerequisites, backend setup, the error codes, the production checklist and the known limitations.

## Commands

| Command | What it does |
| --- | --- |
| `npm ci` | Installs the exact locked dependencies |
| `npm run dev` | Starts the development server on http://localhost:3000 |
| `npm run lint` | Runs ESLint |
| `npm run typecheck` | Generates the Next.js route types, then runs the TypeScript compiler without emitting files |
| `npm run build` | Builds the production bundle |
| `npm run start` | Serves the production build |

## Environment Variables

Copy `.env.example` to `.env` before the first run.

| Variable | Purpose |
| --- | --- |
| `NEXT_PUBLIC_API_URL` | Address of the backend API. It is written into the bundle at build time, so set the production value before `npm run build` |
| `SESSION_COOKIE_NAME` | Name of the backend session cookie. It must equal the backend `SESSION_COOKIE` value |
