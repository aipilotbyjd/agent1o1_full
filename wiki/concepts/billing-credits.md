---
type: concept
status: sourced
sources: 2
last_updated: 2026-05-09
tags: [billing, credits, monetization]
---

# Billing & Credits

**TL;DR**: Workspaces subscribe to a Plan (Stripe) and receive a monthly credit allocation. Additional credits can be purchased via Credit Packs. Executions consume credits.

---

## Models

| Model | Purpose |
|-------|---------|
| `Plan` | Defines subscription tiers (Free, Pro, Team, etc.) |
| `Subscription` | Active Stripe subscription for a workspace (via Cashier) |
| `CreditPack` | A purchasable bundle of extra credits |
| `CreditTransaction` | Ledger entry for every credit movement |
| `UsageDailySnapshot` | Daily rollup of usage per workspace |
| `WorkspaceUsagePeriod` | Tracks credits used in the current billing period |

## Credit Flow

```
Plan subscription → monthly credit grant (billing:reset-monthly-credits)
Purchase CreditPack → credit grant (CreditTransaction: type purchase)
Execute workflow nodes → credit deduction (CreditTransaction: type usage)
```

## Enums

- `SubscriptionStatus`: `active`, `trialing`, `past_due`, `cancelled`, etc.
- `BillingInterval`: `monthly`, `yearly`
- `CreditPackStatus`: `active`, `expired`, `depleted`
- `CreditTransactionType`: `grant`, `usage`, `refund`, `purchase`, `adjustment`

## Scheduled Jobs

| Command | Schedule | Purpose |
|---------|----------|---------|
| `billing:snapshot-daily-usage` | Daily 00:05 | Creates `UsageDailySnapshot` |
| `billing:expire-credit-packs` | Daily 00:10 | Marks expired packs |
| `billing:reset-monthly-credits` | Daily | Resets monthly credit allocation on renewal |

## Billable Entity

The **Workspace** is the billable unit (implements Cashier `Billable` trait). Users belong to workspaces; charges go to the workspace.

## Integration Points

- `BillingController` — Stripe webhook handler and billing management endpoints
- `CreditController` — credit balance, transaction history
- `ConnectorCallAttempt` — raw record of each connector call (for metering)
- `ConnectorMetricDaily` — aggregated daily metrics per connector

## Open Questions

- How are credits denominated? (per workflow run? per node execution? per API call?)
- Is there a free tier with a credit limit?

---

## Sources

- `raw/api-routes-2026-05-09.txt` — confirms `POST /billing/checkout`, `POST /billing/credits`, `GET /billing/portal`, `GET /credits/balance`, `GET /credits/transactions`; note `billing/` and `credits/` are separate frontend modules
- `raw/frontend-api-modules-2026-05-09.txt` — confirms `billing/` and `credits/` as separate API modules
- `backend/composer.json` — confirms `laravel/cashier` is the Stripe integration
- *(no external sources yet — flag: pricing decision doc, credit-cost-per-node table, plan tiers)*
