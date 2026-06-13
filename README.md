# Pius Videos Backend

Laravel 13 backend using Streamline as the application API transport.

## API conventions

- Pass Streamline action slugs such as `auth/guest:login` directly to `ShForm`, `ShTable`, and `shApis`.
- Use the `auth/guest` stream for the `login` and `register` actions.
- Authenticate all other streams with a Sanctum bearer token.
- Do not add conventional application controllers or API routes.
- Return field-keyed validation errors for `sh-form`.
- Return item lists as `{ "status": "success", "data": <Laravel paginator> }` for `sh-table`.

The Vue frontend must use `@iankibetsh/sh-tailwind`, with `sh-form` for every form and `sh-table` for every item listing.

## Frontend

- `/login` uses `ShForm` with the `auth/guest:login` Streamline action.
- `/register` uses `ShForm` with the `auth/guest:register` Streamline action.
- `/users` is authenticated and lists registered users with `ShTable`.
- Run `npm run dev` for local development or `npm run build` for production assets.
