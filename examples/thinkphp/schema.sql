CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    status INTEGER NOT NULL DEFAULT 0,
    owner_id INTEGER NOT NULL
);
CREATE INDEX products_owner ON products (owner_id);
