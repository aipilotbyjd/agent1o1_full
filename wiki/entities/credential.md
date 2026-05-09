---
type: entity
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [entity, security, integrations]
---

# Credential

**TL;DR**: Encrypted storage for third-party auth — API keys, OAuth tokens, and similar secrets used by nodes.

---

## Key Fields

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `workspace_id` | FK | Scoped to workspace |
| `name` | string | User-defined label |
| `type` | string | Credential type slug (e.g. `google_oauth`, `slack_bot_token`) |
| `data` | JSON (encrypted) | The actual secrets |
| `is_valid` | bool | Whether the credential has passed validation |
| `expires_at` | timestamp? | For OAuth tokens with expiry |

## CredentialType

`CredentialType` defines what fields a credential of that type requires:

- `name` — display name
- `slug` — unique identifier used in node definitions
- `schema` — JSON Schema for the credential fields

## OAuth Credentials

OAuth flow uses `OAuthCredentialState` to track the PKCE/state parameter during the auth redirect. A background job (`RefreshOAuthTokenJob`) runs daily to proactively refresh credentials expiring within 7 days.

## Relationships

- belongs to `Workspace`
- referenced by `Node` types via `credential_type` field
- `OAuthCredentialState` — temporary state during OAuth flow

## Security Notes

- `data` field is encrypted at rest (Laravel encryption)
- Credentials are never returned raw — the frontend receives masked versions
- Access scoped to workspace membership

## API

| Method | Path | Action |
|--------|------|--------|
| GET | `/workspaces/{id}/credentials` | List |
| POST | `/workspaces/{id}/credentials` | Create |
| GET | `/workspaces/{id}/credentials/{id}` | Get |
| PUT | `/workspaces/{id}/credentials/{id}` | Update |
| DELETE | `/workspaces/{id}/credentials/{id}` | Delete |
| POST | `/workspaces/{id}/credentials/{id}/test` | Test credential validity |
| GET | `/credential-types` | List available types |
| GET | `/credential-types/{type}` | Get type schema |
| POST | `/workspaces/{id}/oauth/initiate` | Start OAuth flow |
| GET | `/oauth/callback` | **Public** — OAuth redirect handler |

Model: `backend/app/Models/Credential.php`, `CredentialType.php`
Frontend types: `frontend/src/types/credential.type.ts`
API module: `frontend/src/api/modules/credentials/`

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms credentials CRUD + test endpoint + credential-types + `workspaces/{id}/oauth/initiate` + public `oauth/callback` routes
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `credentials/` and `credential-types/` as separate API modules
- `backend/app/Models/Credential.php`, `CredentialType.php` — code references
- *(no external sources yet — flag: encryption-at-rest design notes, OAuth integration spec)*
