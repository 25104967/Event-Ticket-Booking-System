-- Run this ONLY if you already imported schema.sql before password reset
-- support was added, and don't want to drop your existing database.
-- (If you're setting up fresh, schema.sql already includes this table —
-- you don't need to run this file.)

USE event_ticket_system;

CREATE TABLE IF NOT EXISTS Password_Resets (
    Reset_ID     INT AUTO_INCREMENT PRIMARY KEY,
    Account_Type ENUM('customer','staff') NOT NULL,
    Account_ID   INT NOT NULL,
    Token_Hash   VARCHAR(64) NOT NULL UNIQUE,
    Expires_At   DATETIME NOT NULL,
    Used         BOOLEAN NOT NULL DEFAULT FALSE,
    Created_At   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
