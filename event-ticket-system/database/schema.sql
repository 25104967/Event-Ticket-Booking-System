DROP DATABASE IF EXISTS event_ticket_system;
CREATE DATABASE event_ticket_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_ticket_system;

CREATE TABLE Roles (
    Role_ID     INT AUTO_INCREMENT PRIMARY KEY,
    Role_Name   VARCHAR(30) NOT NULL UNIQUE,        -- 'Admin', 'Organizer', 'Staff'
    Description VARCHAR(255) NULL
);

CREATE TABLE Staff (
    Staff_ID       INT AUTO_INCREMENT PRIMARY KEY,
    Staff_User     VARCHAR(50)  NOT NULL UNIQUE,
    Password_Hash  VARCHAR(255) NOT NULL,
    First_Name     VARCHAR(50)  NOT NULL,
    Last_Name      VARCHAR(50)  NOT NULL,
    Email          VARCHAR(100) NOT NULL UNIQUE,
    Role_ID        INT NOT NULL,
    Account_Status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    Created_At     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Role_ID) REFERENCES Roles(Role_ID)
);

CREATE TABLE Customers (
    Customer_ID    INT AUTO_INCREMENT PRIMARY KEY,
    Customer_User  VARCHAR(50)  NOT NULL UNIQUE,
    Password_Hash  VARCHAR(255) NOT NULL,
    First_Name     VARCHAR(50)  NOT NULL,
    Last_Name      VARCHAR(50)  NOT NULL,
    Email_Address  VARCHAR(100) NOT NULL UNIQUE,
    Phone_Number   VARCHAR(20)  NULL,
    Account_Status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    Created_At     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Venues (
    Venue_ID     INT AUTO_INCREMENT PRIMARY KEY,
    Venue_Name   VARCHAR(150) NOT NULL,
    Address      VARCHAR(255) NOT NULL,
    Max_Capacity INT NOT NULL
);

CREATE TABLE Seats (
    Seat_ID      INT AUTO_INCREMENT PRIMARY KEY,
    Venue_ID     INT NOT NULL,
    Seat_Row     VARCHAR(5)  NOT NULL,
    Seat_Number  INT NOT NULL,
    Is_Available BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (Venue_ID) REFERENCES Venues(Venue_ID) ON DELETE CASCADE,
    UNIQUE KEY unique_seat (Venue_ID, Seat_Row, Seat_Number)
);

CREATE TABLE Events (
    Event_ID          INT AUTO_INCREMENT PRIMARY KEY,
    Venue_ID           INT NOT NULL,
    Staff_ID            INT NOT NULL,               -- organizer who created it
    Event_Name         VARCHAR(150) NOT NULL,
    Event_Description  TEXT NULL,
    Category           VARCHAR(50) NULL,
    Poster_Image        VARCHAR(255) NULL,
    Start_Date_Time     DATETIME NOT NULL,
    End_Date_Time        DATETIME NOT NULL,
    Event_Status        ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'published',
    Created_At           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Venue_ID) REFERENCES Venues(Venue_ID),
    FOREIGN KEY (Staff_ID) REFERENCES Staff(Staff_ID)
);

CREATE TABLE Ticket_Tiers (
    Tier_ID             INT AUTO_INCREMENT PRIMARY KEY,
    Event_ID            INT NOT NULL,
    Tier_Name            VARCHAR(50) NOT NULL,
    Price                DECIMAL(10,2) NOT NULL,
    Quantity_Available    INT NOT NULL,
    Quantity_Sold          INT NOT NULL DEFAULT 0,
    FOREIGN KEY (Event_ID) REFERENCES Events(Event_ID) ON DELETE CASCADE
);

CREATE TABLE Transactions (
    Transaction_ID              INT AUTO_INCREMENT PRIMARY KEY,
    Amount_Paid                  DECIMAL(10,2) NOT NULL,
    Payment_Method                VARCHAR(50) NOT NULL,   -- 'GCash','Maya','DragonPay','Mock'
    Transaction_Status             ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
    Transaction_Reference_Number    VARCHAR(100) NOT NULL UNIQUE,
    Transaction_Date                TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Bookings (
    Booking_ID       INT AUTO_INCREMENT PRIMARY KEY,
    Customer_ID       INT NOT NULL,
    Tier_ID            INT NOT NULL,
    Seat_ID             INT NULL,                     -- NULL allowed for GA (no fixed seat)
    Transaction_ID       INT NULL,
    Booking_Date          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Booking_Status          ENUM('pending','confirmed','cancelled','used') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (Customer_ID) REFERENCES Customers(Customer_ID),
    FOREIGN KEY (Tier_ID) REFERENCES Ticket_Tiers(Tier_ID),
    FOREIGN KEY (Seat_ID) REFERENCES Seats(Seat_ID),
    FOREIGN KEY (Transaction_ID) REFERENCES Transactions(Transaction_ID)
);

CREATE TABLE QR_Code (
    QR_Code_ID     INT AUTO_INCREMENT PRIMARY KEY,
    Booking_ID      INT NOT NULL UNIQUE,
    QR_Data          VARCHAR(255) NOT NULL UNIQUE,   -- signed reference string encoded into the QR
    Generated_At      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Expiry_Date        DATETIME NOT NULL,
    Is_Used             BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (Booking_ID) REFERENCES Bookings(Booking_ID) ON DELETE CASCADE
);

CREATE TABLE Password_Resets (
    Reset_ID     INT AUTO_INCREMENT PRIMARY KEY,
    Account_Type ENUM('customer','staff') NOT NULL,
    Account_ID   INT NOT NULL,
    Token_Hash   VARCHAR(64) NOT NULL UNIQUE,
    Expires_At   DATETIME NOT NULL,
    Used         BOOLEAN NOT NULL DEFAULT FALSE,
    Created_At   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_events_start ON Events(Start_Date_Time);
CREATE INDEX idx_bookings_status ON Bookings(Booking_Status);
CREATE INDEX idx_bookings_tier ON Bookings(Tier_ID);
CREATE INDEX idx_bookings_seat ON Bookings(Seat_ID);

CREATE TABLE Login_Attempts (
    Attempt_ID   INT AUTO_INCREMENT PRIMARY KEY,
    Identifier   VARCHAR(100) NOT NULL,
    Account_Type ENUM('customer','staff') NOT NULL,
    Ip_Address   VARCHAR(45) NULL,
    Was_Success  BOOLEAN NOT NULL DEFAULT FALSE,
    Attempted_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_login_attempts_lookup ON Login_Attempts(Identifier, Account_Type, Attempted_At);
