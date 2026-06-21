# GAN-TECH Merchant Payment Operations SaaS Database Schema

This schema is the target logical database model for the GANPay SaaS backend. The first implementation should use PostgreSQL.

## Naming Rules

- Every tenant-owned table must contain `merchant_id`.
- Money values must be stored as integer minor units where possible, for example cents or shillings depending on currency rules.
- Every externally exposed object should have a public prefixed id such as `pay_`, `pi_`, `pl_`, `wh_`, `stl_`, or `cus_`.
- Raw provider payloads must be stored in JSONB fields for audit and reconciliation.
- Mutable business records use timestamps; ledger entries are immutable.

## Core Tables

### merchants

```sql
CREATE TABLE merchants (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    legal_name VARCHAR(220),
    business_type VARCHAR(80),
    registration_number VARCHAR(120),
    tax_pin VARCHAR(80),
    country CHAR(2) NOT NULL DEFAULT 'KE',
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    verification_status VARCHAR(40) NOT NULL DEFAULT 'unverified',
    default_currency CHAR(3) NOT NULL DEFAULT 'KES',
    test_mode_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    live_mode_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### merchant_users

```sql
CREATE TABLE merchant_users (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    user_id BIGINT NOT NULL,
    role VARCHAR(60) NOT NULL DEFAULT 'owner',
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    UNIQUE (merchant_id, user_id)
);
```

### merchant_credentials

```sql
CREATE TABLE merchant_credentials (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    provider VARCHAR(50) NOT NULL DEFAULT 'mpesa',
    environment VARCHAR(20) NOT NULL,
    short_code VARCHAR(30),
    consumer_key_encrypted TEXT NOT NULL,
    consumer_secret_encrypted TEXT NOT NULL,
    passkey_encrypted TEXT,
    initiator_name_encrypted TEXT,
    security_credential_encrypted TEXT,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    UNIQUE (merchant_id, provider, environment)
);
```

### api_keys

```sql
CREATE TABLE api_keys (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    public_key VARCHAR(80) NOT NULL UNIQUE,
    secret_hash VARCHAR(255) NOT NULL,
    name VARCHAR(160) NOT NULL,
    environment VARCHAR(20) NOT NULL DEFAULT 'test',
    scopes JSONB NOT NULL DEFAULT '[]'::jsonb,
    ip_allowlist JSONB NOT NULL DEFAULT '[]'::jsonb,
    last_used_at TIMESTAMP,
    revoked_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### customers

```sql
CREATE TABLE customers (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(180),
    email VARCHAR(180),
    phone VARCHAR(30),
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### payment_intents

```sql
CREATE TABLE payment_intents (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    customer_id BIGINT REFERENCES customers(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    status VARCHAR(60) NOT NULL DEFAULT 'created',
    provider VARCHAR(50) NOT NULL DEFAULT 'mpesa',
    payment_method VARCHAR(80) NOT NULL DEFAULT 'mpesa_stk',
    merchant_reference VARCHAR(180),
    description TEXT,
    checkout_request_id VARCHAR(120),
    merchant_request_id VARCHAR(120),
    provider_transaction_id VARCHAR(120),
    provider_receipt VARCHAR(120),
    phone VARCHAR(30),
    expires_at TIMESTAMP,
    paid_at TIMESTAMP,
    failed_at TIMESTAMP,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### payment_transactions

```sql
CREATE TABLE payment_transactions (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    payment_intent_id BIGINT NOT NULL REFERENCES payment_intents(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    type VARCHAR(60) NOT NULL,
    status VARCHAR(60) NOT NULL,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    provider VARCHAR(50) NOT NULL DEFAULT 'mpesa',
    provider_reference VARCHAR(180),
    raw_request JSONB NOT NULL DEFAULT '{}'::jsonb,
    raw_response JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### payment_links

```sql
CREATE TABLE payment_links (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    description TEXT,
    amount_minor BIGINT,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    success_url TEXT,
    cancel_url TEXT,
    expires_at TIMESTAMP,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### checkout_sessions

```sql
CREATE TABLE checkout_sessions (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    payment_intent_id BIGINT REFERENCES payment_intents(id),
    payment_link_id BIGINT REFERENCES payment_links(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    status VARCHAR(60) NOT NULL DEFAULT 'open',
    customer_phone VARCHAR(30),
    success_url TEXT,
    cancel_url TEXT,
    expires_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### webhook_endpoints

```sql
CREATE TABLE webhook_endpoints (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    url TEXT NOT NULL,
    secret_encrypted TEXT NOT NULL,
    events JSONB NOT NULL DEFAULT '[]'::jsonb,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### webhook_deliveries

```sql
CREATE TABLE webhook_deliveries (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    webhook_endpoint_id BIGINT NOT NULL REFERENCES webhook_endpoints(id),
    event_type VARCHAR(120) NOT NULL,
    payload JSONB NOT NULL,
    status VARCHAR(60) NOT NULL DEFAULT 'pending',
    attempt_count INT NOT NULL DEFAULT 0,
    next_attempt_at TIMESTAMP,
    last_status_code INT,
    last_error TEXT,
    delivered_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### ledger_accounts

```sql
CREATE TABLE ledger_accounts (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT REFERENCES merchants(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    account_type VARCHAR(80) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    name VARCHAR(180) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### ledger_entries

```sql
CREATE TABLE ledger_entries (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT REFERENCES merchants(id),
    ledger_account_id BIGINT NOT NULL REFERENCES ledger_accounts(id),
    payment_intent_id BIGINT REFERENCES payment_intents(id),
    settlement_id BIGINT,
    direction VARCHAR(10) NOT NULL,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    description TEXT NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL
);
```

### refunds, reversals, settlements

```sql
CREATE TABLE refunds (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    payment_intent_id BIGINT NOT NULL REFERENCES payment_intents(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    status VARCHAR(60) NOT NULL DEFAULT 'created',
    reason TEXT,
    raw_response JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE reversals (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    payment_intent_id BIGINT NOT NULL REFERENCES payment_intents(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    status VARCHAR(60) NOT NULL DEFAULT 'created',
    provider_reference VARCHAR(180),
    raw_response JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE settlements (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    public_id VARCHAR(40) NOT NULL UNIQUE,
    amount_minor BIGINT NOT NULL,
    fee_minor BIGINT NOT NULL DEFAULT 0,
    net_amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    status VARCHAR(60) NOT NULL DEFAULT 'created',
    period_start TIMESTAMP NOT NULL,
    period_end TIMESTAMP NOT NULL,
    approved_at TIMESTAMP,
    paid_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE settlement_items (
    id BIGSERIAL PRIMARY KEY,
    settlement_id BIGINT NOT NULL REFERENCES settlements(id),
    payment_intent_id BIGINT NOT NULL REFERENCES payment_intents(id),
    amount_minor BIGINT NOT NULL,
    fee_minor BIGINT NOT NULL DEFAULT 0,
    net_amount_minor BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL
);
```

### audit_logs and idempotency_keys

```sql
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT REFERENCES merchants(id),
    actor_user_id BIGINT,
    action VARCHAR(160) NOT NULL,
    subject_type VARCHAR(120),
    subject_id VARCHAR(120),
    ip_address VARCHAR(80),
    user_agent TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE idempotency_keys (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    key VARCHAR(180) NOT NULL,
    request_hash VARCHAR(128) NOT NULL,
    response_body JSONB,
    status_code INT,
    created_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    UNIQUE (merchant_id, key)
);
```

## Required Indexes

```sql
CREATE INDEX idx_payment_intents_merchant_status ON payment_intents (merchant_id, status);
CREATE INDEX idx_payment_intents_checkout_request ON payment_intents (checkout_request_id);
CREATE INDEX idx_payment_intents_provider_receipt ON payment_intents (provider_receipt);
CREATE INDEX idx_payment_transactions_payment ON payment_transactions (payment_intent_id);
CREATE INDEX idx_webhook_deliveries_pending ON webhook_deliveries (status, next_attempt_at);
CREATE INDEX idx_ledger_entries_merchant_created ON ledger_entries (merchant_id, created_at);
CREATE INDEX idx_settlements_merchant_status ON settlements (merchant_id, status);
```
