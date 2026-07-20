CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    totp_secret TEXT NULL,
    totp_enabled INTEGER NOT NULL DEFAULT 0,
    totp_confirmed_at TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(64) NULL,
    password_hash VARCHAR(255) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    token_hash VARCHAR(128) NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used_at TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    external_product_id VARCHAR(128) NULL,
    external_offer_id VARCHAR(128) NULL,
    slug VARCHAR(128) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(64) NULL,
    kind VARCHAR(32) NOT NULL DEFAULT 'plugin',
    description TEXT NULL,
    price REAL NULL,
    currency VARCHAR(8) NULL,
    checkout_url TEXT NULL,
    image_path VARCHAR(512) NULL,
    plugin_zip_path VARCHAR(512) NULL,
    plugin_zip_filename VARCHAR(255) NULL,
    plugin_zip_sha256 VARCHAR(128) NULL,
    plugin_zip_size INTEGER NULL,
    is_published INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    webhook_token VARCHAR(64) NULL,
    webhook_secret VARCHAR(128) NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS product_combo_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    combo_product_id INTEGER NOT NULL,
    included_product_id INTEGER NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    UNIQUE(combo_product_id, included_product_id),
    FOREIGN KEY (combo_product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (included_product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_combo_items_combo ON product_combo_items(combo_product_id);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    external_order_id VARCHAR(64) NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    product_id INTEGER NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'completed',
    amount REAL NULL,
    currency VARCHAR(8) NULL,
    payment_method VARCHAR(64) NULL,
    raw_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS refund_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    processed_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_refund_requests_order ON refund_requests(order_id);
CREATE INDEX IF NOT EXISTS idx_refund_requests_status ON refund_requests(status);

CREATE TABLE IF NOT EXISTS entitlements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    order_id INTEGER NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(customer_id, product_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS licenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    license_key VARCHAR(64) NOT NULL UNIQUE,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    max_activations INTEGER NOT NULL DEFAULT 1,
    customer_id INTEGER NULL,
    product_id INTEGER NULL,
    order_id INTEGER NULL,
    customer_note TEXT NULL,
    expires_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS activations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    license_id INTEGER NOT NULL,
    domain VARCHAR(255) NOT NULL,
    install_id VARCHAR(64) NOT NULL,
    is_localhost INTEGER NOT NULL DEFAULT 0,
    app_version VARCHAR(64) NULL,
    ip VARCHAR(64) NULL,
    bound_at TEXT NULL,
    last_seen_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(license_id, install_id),
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lesson_sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(128) NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS lessons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(128) NOT NULL UNIQUE,
    body_markdown TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    published INTEGER NOT NULL DEFAULT 1,
    docs_public INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (section_id) REFERENCES lesson_sections(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS webhook_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_name VARCHAR(64) NOT NULL,
    external_order_id VARCHAR(64) NULL,
    payload_json TEXT NOT NULL,
    processed INTEGER NOT NULL DEFAULT 0,
    process_result TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(128) PRIMARY KEY,
    value TEXT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS outbound_webhooks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    bearer_token TEXT NULL,
    events TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS outbound_webhook_deliveries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    outbound_webhook_id INTEGER NOT NULL,
    event_name VARCHAR(64) NOT NULL,
    request_body TEXT NULL,
    response_status INTEGER NULL,
    response_body TEXT NULL,
    ok INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (outbound_webhook_id) REFERENCES outbound_webhooks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS releases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version VARCHAR(64) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(512) NOT NULL,
    sha256 VARCHAR(64) NOT NULL,
    size_bytes INTEGER NOT NULL DEFAULT 0,
    notes TEXT NULL,
    is_current INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    schema_filename VARCHAR(255) NULL,
    schema_storage_path VARCHAR(512) NULL,
    schema_sha256 VARCHAR(64) NULL,
    schema_size_bytes INTEGER NULL
);

CREATE TABLE IF NOT EXISTS install_download_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash VARCHAR(128) NOT NULL UNIQUE,
    release_id INTEGER NOT NULL,
    license_id INTEGER NOT NULL,
    expires_at TEXT NOT NULL,
    max_uses INTEGER NOT NULL DEFAULT 2,
    uses INTEGER NOT NULL DEFAULT 0,
    created_ip VARCHAR(64) NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_activations_license ON activations(license_id);
CREATE INDEX IF NOT EXISTS idx_activations_domain ON activations(domain);
CREATE INDEX IF NOT EXISTS idx_entitlements_customer ON entitlements(customer_id);
CREATE INDEX IF NOT EXISTS idx_licenses_customer ON licenses(customer_id);
CREATE INDEX IF NOT EXISTS idx_webhook_events_order ON webhook_events(external_order_id);
CREATE INDEX IF NOT EXISTS idx_lessons_section ON lessons(section_id);
CREATE INDEX IF NOT EXISTS idx_outbound_deliveries_hook ON outbound_webhook_deliveries(outbound_webhook_id);
CREATE INDEX IF NOT EXISTS idx_releases_current ON releases(is_current);
CREATE INDEX IF NOT EXISTS idx_install_tokens_hash ON install_download_tokens(token_hash);
