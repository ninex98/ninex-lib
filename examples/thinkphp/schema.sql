CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    status INTEGER NOT NULL DEFAULT 0,
    tenant_id INTEGER NOT NULL
);
CREATE INDEX products_tenant ON products (tenant_id);
