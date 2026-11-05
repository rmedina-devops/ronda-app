-- Business dates: 'YYYY-MM-DD' (Chile time). Timestamps (*_at): UTC.

CREATE TABLE person (
    id             INTEGER PRIMARY KEY,
    type_document  TEXT NOT NULL CHECK (type_document IN ('rut', 'passport', 'other')),
    doc_hash       TEXT NOT NULL,
    doc_encrypted  TEXT NOT NULL,
    full_name      TEXT NOT NULL,
    created_by     INTEGER REFERENCES account (id),
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (type_document, doc_hash)
) STRICT;

CREATE TABLE account (
    id                     INTEGER PRIMARY KEY,
    person_id              INTEGER NOT NULL UNIQUE REFERENCES person (id),
    username               TEXT NOT NULL UNIQUE,
    password_hash          TEXT NOT NULL,
    account_role           TEXT NOT NULL CHECK (account_role IN ('admin', 'manager', 'user')),
    is_active              INTEGER NOT NULL DEFAULT 0 CHECK (is_active IN (0, 1)),
    must_change_password   INTEGER NOT NULL DEFAULT 1 CHECK (must_change_password IN (0, 1)),
    reset_code_hash        TEXT,
    reset_code_expires_at  TEXT,
    failed_attempts        INTEGER NOT NULL DEFAULT 0,
    blocked_until          TEXT,
    created_at             TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at             TEXT NOT NULL DEFAULT (datetime('now'))
) STRICT;

CREATE TABLE playgame (
    id               INTEGER PRIMARY KEY,
    manager_id       INTEGER NOT NULL REFERENCES account (id),
    playgame_name    TEXT NOT NULL,
    fee              INTEGER NOT NULL CHECK (fee > 0),
    amount_numbers   INTEGER NOT NULL CHECK (amount_numbers >= 2),
    mode_assign      TEXT NOT NULL CHECK (mode_assign IN ('draw', 'manual')),
    start_date       TEXT NOT NULL,
    playgame_status  TEXT NOT NULL DEFAULT 'draft'
                     CHECK (playgame_status IN ('draft', 'in_progress', 'finished')),
    finished_at      TEXT,
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (manager_id, playgame_name)
) STRICT;

CREATE TABLE numbers (
    id                 INTEGER PRIMARY KEY,
    playgame_id        INTEGER NOT NULL REFERENCES playgame (id) ON DELETE CASCADE,
    position           INTEGER NOT NULL CHECK (position >= 1),
    numbers_period     TEXT NOT NULL,
    payment_due_date   TEXT NOT NULL,
    delivery_due_date  TEXT NOT NULL,
    numbers_status     TEXT NOT NULL DEFAULT 'pending'
                       CHECK (numbers_status IN ('pending', 'delivered')),
    UNIQUE (playgame_id, position)
) STRICT;

CREATE TABLE assignment (
    id                  INTEGER PRIMARY KEY,
    playgame_id         INTEGER NOT NULL REFERENCES playgame (id) ON DELETE CASCADE,
    number_id           INTEGER REFERENCES numbers (id) ON DELETE CASCADE,
    person_id           INTEGER NOT NULL REFERENCES person (id),
    assignment_group    INTEGER,
    assignment_from     TEXT NOT NULL,
    assignment_upto     TEXT,
    assignment_status   TEXT NOT NULL DEFAULT 'enrolled'
                        CHECK (assignment_status IN ('enrolled', 'active', 'leaving', 'ended')),
    installment_amount  INTEGER CHECK (installment_amount >= 0),
    delivery_amount     INTEGER CHECK (delivery_amount >= 0),
    created_at          TEXT NOT NULL DEFAULT (datetime('now'))
) STRICT;

CREATE UNIQUE INDEX ux_assignment_number_person
    ON assignment (number_id, person_id)
    WHERE number_id IS NOT NULL AND assignment_status <> 'ended';
CREATE INDEX ix_assignment_playgame ON assignment (playgame_id);
CREATE INDEX ix_assignment_person   ON assignment (person_id);

CREATE TABLE payment (
    id              INTEGER PRIMARY KEY,
    assignment_id   INTEGER NOT NULL REFERENCES assignment (id) ON DELETE CASCADE,
    payment_period  TEXT NOT NULL,
    amount          INTEGER NOT NULL CHECK (amount >= 0),
    paid_date       TEXT,
    payment_status  TEXT NOT NULL DEFAULT 'pending'
                    CHECK (payment_status IN ('pending', 'on_time', 'late', 'compensated')),
    registered_by   INTEGER REFERENCES account (id),
    confirmed_at    TEXT,
    UNIQUE (assignment_id, payment_period)
) STRICT;

CREATE TABLE delivery (
    id               INTEGER PRIMARY KEY,
    assignment_id    INTEGER NOT NULL UNIQUE REFERENCES assignment (id) ON DELETE CASCADE,
    amount           INTEGER NOT NULL CHECK (amount >= 0),
    delivered_date   TEXT,
    delivery_status  TEXT NOT NULL DEFAULT 'scheduled'
                     CHECK (delivery_status IN ('scheduled', 'delivered')),
    registered_by    INTEGER REFERENCES account (id),
    confirmed_at     TEXT
) STRICT;

CREATE TABLE observation (
    id                       INTEGER PRIMARY KEY,
    payment_id               INTEGER REFERENCES payment (id) ON DELETE CASCADE,
    delivery_id              INTEGER REFERENCES delivery (id) ON DELETE CASCADE,
    observation_description  TEXT NOT NULL,
    observation_status       TEXT NOT NULL DEFAULT 'open'
                             CHECK (observation_status IN ('open', 'resolved')),
    created_by               INTEGER NOT NULL REFERENCES account (id),
    resolved_by              INTEGER REFERENCES account (id),
    created_at               TEXT NOT NULL DEFAULT (datetime('now')),
    CHECK ((payment_id IS NULL) <> (delivery_id IS NULL))
) STRICT;

CREATE TABLE random (
    id             INTEGER PRIMARY KEY,
    playgame_id    INTEGER NOT NULL UNIQUE REFERENCES playgame (id) ON DELETE CASCADE,
    random_status  TEXT NOT NULL DEFAULT 'scheduled'
                   CHECK (random_status IN ('scheduled', 'live', 'finished')),
    scheduled_for  TEXT,
    started_at     TEXT,
    finished_at    TEXT,
    created_by     INTEGER NOT NULL REFERENCES account (id)
) STRICT;

CREATE TABLE random_step (
    id                 INTEGER PRIMARY KEY,
    random_id          INTEGER NOT NULL REFERENCES random (id) ON DELETE CASCADE,
    random_step_order  INTEGER NOT NULL,
    number_id          INTEGER NOT NULL REFERENCES numbers (id),
    drawn_at           TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (random_id, random_step_order),
    UNIQUE (random_id, number_id)
) STRICT;

-- No foreign keys on purpose: it survives the deletion of what it describes.
CREATE TABLE audit (
    id            INTEGER PRIMARY KEY,
    entity        TEXT NOT NULL,
    entity_id     INTEGER,
    audit_action  TEXT NOT NULL,
    value_old     TEXT,
    value_new     TEXT,
    username      TEXT,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
) STRICT;
CREATE INDEX ix_audit_entity ON audit (entity, entity_id);
CREATE INDEX ix_audit_date   ON audit (created_at);