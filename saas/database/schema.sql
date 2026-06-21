CREATE TABLE merchants (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    legal_name VARCHAR(220),
    country CHAR(2) NOT NULL DEFAULT 'KE',
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    verification_status VARCHAR(40) NOT NULL DEFAULT 'unverified',
    default_currency CHAR(3) NOT NULL DEFAULT 'KES',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE api_keys (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
    public_key VARCHAR(80) NOT NULL UNIQUE,
    secret_hash VARCHAR(255) NOT NULL,
    name VARCHAR(160) NOT NULL,
    environment VARCHAR(20) NOT NULL DEFAULT 'test',
    scopes JSONB NOT NULL DEFAULT '[]'::jsonb,
    revoked_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE payment_intents (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT NOT NULL REFERENCES merchants(id),
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
    paid_at TIMESTAMP,
    failed_at TIMESTAMP,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

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

CREATE TABLE ledger_entries (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT REFERENCES merchants(id),
    ledger_account_id BIGINT NOT NULL,
    payment_intent_id BIGINT REFERENCES payment_intents(id),
    direction VARCHAR(10) NOT NULL,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    description TEXT NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_payment_intents_merchant_status ON payment_intents (merchant_id, status);
CREATE INDEX idx_payment_intents_checkout_request ON payment_intents (checkout_request_id);
CREATE INDEX idx_payment_links_merchant_status ON payment_links (merchant_id, status);
CREATE INDEX idx_ledger_entries_merchant_created ON ledger_entries (merchant_id, created_at);
