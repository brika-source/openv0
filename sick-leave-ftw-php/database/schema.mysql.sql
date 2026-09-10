-- Corporate Sick Leave & Fit-to-Work Management System
-- Reference schema for mysql, generated from app/Schema.php.
-- Generated 2026-09-10. Do not edit by hand: the application
-- creates these tables itself via "php bin/console.php install".

CREATE TABLE IF NOT EXISTS users (
        id            VARCHAR(32)       NOT NULL PRIMARY KEY,
        name          VARCHAR(160) NOT NULL,
        email         VARCHAR(190) NOT NULL,
        dept          VARCHAR(160) NOT NULL,
        role          VARCHAR(20)  NOT NULL,
        manager_id    VARCHAR(32)       NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_active     INTEGER      NOT NULL DEFAULT 1,
        created_at    VARCHAR(19)        NOT NULL
    );

CREATE TABLE IF NOT EXISTS sick_cases (
        case_id                    VARCHAR(32)        NOT NULL PRIMARY KEY,
        employee_id                VARCHAR(32)        NOT NULL,
        diagnosis                  TEXT         NOT NULL,
        specialty                  VARCHAR(60)  NOT NULL,
        from_date                  VARCHAR(10)        NOT NULL,
        to_date                    VARCHAR(10)        NOT NULL,
        status                     VARCHAR(60)  NOT NULL,
        rtw_required               INTEGER      NULL,
        rtw_outcome                VARCHAR(60)  NULL,
        rtw_notes                  TEXT         NULL,
        rtw_by                     VARCHAR(160) NULL,
        rtw_at                     VARCHAR(19)        NULL,
        restriction_details        TEXT         NULL,
        restriction_review_date    VARCHAR(10)        NULL,
        created_at                 VARCHAR(19)        NOT NULL,
        updated_at                 VARCHAR(19)        NOT NULL
    );

CREATE TABLE IF NOT EXISTS case_history (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        case_id    VARCHAR(32)        NOT NULL,
        ts         VARCHAR(19)        NOT NULL,
        actor      VARCHAR(190) NOT NULL,
        action     VARCHAR(120) NOT NULL,
        comment    TEXT         NULL
    );

CREATE TABLE IF NOT EXISTS case_documents (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        case_id     VARCHAR(32)        NOT NULL,
        name        VARCHAR(200) NOT NULL,
        version     INTEGER      NOT NULL DEFAULT 1,
        stored_name VARCHAR(200) NULL,
        size_bytes  INTEGER      NOT NULL DEFAULT 0,
        mime        VARCHAR(120) NULL,
        uploaded_at VARCHAR(19)        NOT NULL,
        uploaded_by VARCHAR(32)        NULL
    );

CREATE TABLE IF NOT EXISTS case_extensions (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        case_id   VARCHAR(32) NOT NULL,
        from_date VARCHAR(10) NOT NULL,
        to_date   VARCHAR(10) NOT NULL,
        ts        VARCHAR(19) NOT NULL
    );

CREATE TABLE IF NOT EXISTS ftw_requests (
        id                      VARCHAR(32)        NOT NULL PRIMARY KEY,
        employee_id             VARCHAR(32)        NOT NULL,
        requester_id            VARCHAR(32)        NULL,
        requester_label         VARCHAR(190) NOT NULL,
        diagnosis               TEXT         NOT NULL,
        med_history             TEXT         NULL,
        job_free_text           TEXT         NULL,
        status                  VARCHAR(60)  NOT NULL,
        restriction_details     TEXT         NULL,
        restriction_review_date VARCHAR(10)        NULL,
        diagnostic_details      TEXT         NULL,
        diagnostic_at           VARCHAR(19)        NULL,
        created_at              VARCHAR(19)        NOT NULL,
        updated_at              VARCHAR(19)        NOT NULL
    );

CREATE TABLE IF NOT EXISTS ftw_job_demands (
        id     INT AUTO_INCREMENT PRIMARY KEY,
        ftw_id VARCHAR(32)        NOT NULL,
        demand VARCHAR(120) NOT NULL
    );

CREATE TABLE IF NOT EXISTS ftw_attachments (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        ftw_id      VARCHAR(32)        NOT NULL,
        name        VARCHAR(200) NOT NULL,
        stored_name VARCHAR(200) NULL,
        size_bytes  INTEGER      NOT NULL DEFAULT 0,
        mime        VARCHAR(120) NULL,
        uploaded_at VARCHAR(19)        NOT NULL,
        uploaded_by VARCHAR(32)        NULL
    );

CREATE TABLE IF NOT EXISTS ftw_history (
        id      INT AUTO_INCREMENT PRIMARY KEY,
        ftw_id  VARCHAR(32)        NOT NULL,
        ts      VARCHAR(19)        NOT NULL,
        actor   VARCHAR(190) NOT NULL,
        action  VARCHAR(120) NOT NULL,
        comment TEXT         NULL
    );

CREATE TABLE IF NOT EXISTS notifications (
        id      INT AUTO_INCREMENT PRIMARY KEY,
        ts      VARCHAR(19)        NOT NULL,
        event   VARCHAR(120) NOT NULL,
        message TEXT         NOT NULL,
        ref_id  VARCHAR(32)  NULL
    );

CREATE TABLE IF NOT EXISTS notification_targets (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        notification_id INTEGER      NOT NULL,
        target          VARCHAR(64)  NOT NULL
    );

CREATE TABLE IF NOT EXISTS notification_reads (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        notification_id INTEGER NOT NULL,
        user_id         VARCHAR(32)   NOT NULL,
        read_at         VARCHAR(19)   NOT NULL
    );

CREATE TABLE IF NOT EXISTS settings (
        setting_key   VARCHAR(60)  NOT NULL PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    );

CREATE TABLE IF NOT EXISTS sequences (
        name  VARCHAR(30) NOT NULL PRIMARY KEY,
        value INTEGER     NOT NULL DEFAULT 0
    );

CREATE TABLE IF NOT EXISTS audit_log (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        ts        VARCHAR(19)        NOT NULL,
        user_id   VARCHAR(32)        NULL,
        user_name VARCHAR(160) NULL,
        role      VARCHAR(20)  NULL,
        portal    VARCHAR(20)  NULL,
        action    VARCHAR(80)  NOT NULL,
        entity    VARCHAR(40)  NULL,
        entity_id VARCHAR(64)  NULL,
        detail    TEXT         NULL,
        ip        VARCHAR(45)  NULL
    );

CREATE UNIQUE INDEX idx_users_email ON users (email);
CREATE INDEX idx_users_role ON users (role);
CREATE INDEX idx_users_manager ON users (manager_id);
CREATE INDEX idx_cases_employee ON sick_cases (employee_id);
CREATE INDEX idx_cases_status ON sick_cases (status);
CREATE INDEX idx_cases_from ON sick_cases (from_date);
CREATE INDEX idx_case_history_case ON case_history (case_id, ts);
CREATE INDEX idx_case_docs_case ON case_documents (case_id);
CREATE INDEX idx_case_ext_case ON case_extensions (case_id);
CREATE INDEX idx_ftw_employee ON ftw_requests (employee_id);
CREATE INDEX idx_ftw_status ON ftw_requests (status);
CREATE INDEX idx_ftw_demands ON ftw_job_demands (ftw_id);
CREATE INDEX idx_ftw_att ON ftw_attachments (ftw_id);
CREATE INDEX idx_ftw_history ON ftw_history (ftw_id, ts);
CREATE INDEX idx_notif_ts ON notifications (ts);
CREATE INDEX idx_notif_target ON notification_targets (target);
CREATE INDEX idx_notif_target_nid ON notification_targets (notification_id);
CREATE UNIQUE INDEX idx_notif_read ON notification_reads (notification_id, user_id);
CREATE INDEX idx_audit_ts ON audit_log (ts);
CREATE INDEX idx_audit_entity ON audit_log (entity, entity_id);
