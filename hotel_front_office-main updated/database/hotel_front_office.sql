-- ============================================================
-- Hotel Front Office System - Database Schema
-- Database: hotel_front_office (matches config/database.php)
-- Engine:   MySQL 5.7+ / MariaDB 10.3+ (InnoDB, utf8mb4)
--
-- Import:   mysql -u root -p < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS hotel_front_office
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hotel_front_office;


SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reservation_guests;
DROP TABLE IF EXISTS room_transfers;
DROP TABLE IF EXISTS early_late_records;
DROP TABLE IF EXISTS guest_requests;
DROP TABLE IF EXISTS guest_folio;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS guests;
SET FOREIGN_KEY_CHECKS = 1;


CREATE TABLE guests (
    guest_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL DEFAULT '',  -- optional
    phone       VARCHAR(30)  NOT NULL DEFAULT '',
    address     TEXT         NOT NULL,
    id_type     VARCHAR(50)  NOT NULL DEFAULT '',  -- Passport, Driver's License, National ID, School ID, Other
    id_number   VARCHAR(100) NOT NULL DEFAULT '',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (guest_id),
    KEY idx_guests_name  (last_name, first_name),
    KEY idx_guests_email (email),
    KEY idx_guests_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE rooms (
    room_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_number  VARCHAR(20)  NOT NULL,
    room_type    ENUM('Standard','Deluxe','Suite') NOT NULL DEFAULT 'Standard',
    rate         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    room_status  ENUM('Available','Occupied','Maintenance','Reserved','Cleaning')
                 NOT NULL DEFAULT 'Available',
    PRIMARY KEY (room_id),
    UNIQUE KEY uq_rooms_number (room_number),
    KEY idx_rooms_status (room_status)
) ENGINE=InnoDB;


CREATE TABLE reservations (
    reservation_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    guest_id            INT UNSIGNED NOT NULL,
    room_id             INT UNSIGNED NULL,           -- NULL = not yet assigned
    check_in            DATE NOT NULL,
    check_out           DATE NOT NULL,
    adults              TINYINT UNSIGNED NOT NULL DEFAULT 1,
    children            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    -- 'Walk-in' is inserted by walk_in/ (other values kept for older data)
    booking_source      ENUM('Walk-in','Phone','Website','Travel Agency','Group')
                        NOT NULL DEFAULT 'Walk-in',
    reservation_status  ENUM('Pending','Confirmed','Checked-in','Checked-out','Cancelled','No-show')
                        NOT NULL DEFAULT 'Pending',
    deposit             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cancellation_reason TEXT NULL,
    cancelled_at        DATETIME NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reservation_id),
    KEY idx_res_guest   (guest_id),
    KEY idx_res_room_dates (room_id, check_in, check_out),
    KEY idx_res_status  (reservation_status),
    KEY idx_res_checkin (check_in),
    KEY idx_res_checkout(check_out),
    KEY idx_res_source  (booking_source),
    CONSTRAINT fk_res_guest FOREIGN KEY (guest_id)
        REFERENCES guests (guest_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_res_room FOREIGN KEY (room_id)
        REFERENCES rooms (room_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_res_dates CHECK (check_out > check_in),
    CONSTRAINT chk_res_deposit CHECK (deposit >= 0)
) ENGINE=InnoDB;


CREATE TABLE guest_folio (
    folio_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id    INT UNSIGNED NOT NULL,
    transaction_type  ENUM('Charge','Payment') NOT NULL,
    description       VARCHAR(255) NOT NULL,
    amount            DECIMAL(10,2) NOT NULL,
    transaction_date  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (folio_id),
    KEY idx_folio_reservation (reservation_id, transaction_date),
    CONSTRAINT fk_folio_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations (reservation_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_folio_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE guest_requests (
    request_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    guest_id        INT UNSIGNED NOT NULL,
    request_type    ENUM('Extra Towels','Room Cleaning','Maintenance',
                         'Food Request','Wake-up Call','Other') NOT NULL,
    description     TEXT NOT NULL,
    request_status  ENUM('Pending','In Progress','Completed','Cancelled')
                    NOT NULL DEFAULT 'Pending',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    KEY idx_req_guest  (guest_id),
    KEY idx_req_status (request_status),
    CONSTRAINT fk_req_guest FOREIGN KEY (guest_id)
        REFERENCES guests (guest_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE early_late_records (
    record_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id   INT UNSIGNED NOT NULL,
    adjustment_type  ENUM('Early Check-in','Late Check-out') NOT NULL,
    actual_time      DATETIME NOT NULL,
    reason           TEXT NOT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (record_id),
    KEY idx_el_reservation (reservation_id),
    CONSTRAINT fk_el_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations (reservation_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE room_transfers (
    transfer_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id   INT UNSIGNED NOT NULL,
    old_room_id      INT UNSIGNED NOT NULL,
    new_room_id      INT UNSIGNED NOT NULL,
    transfer_reason  TEXT NOT NULL,
    transfer_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (transfer_id),
    KEY idx_rt_reservation (reservation_id),
    KEY idx_rt_old_room (old_room_id),
    KEY idx_rt_new_room (new_room_id),
    CONSTRAINT fk_rt_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations (reservation_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rt_old_room FOREIGN KEY (old_room_id)
        REFERENCES rooms (room_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rt_new_room FOREIGN KEY (new_room_id)
        REFERENCES rooms (room_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- reservation_guests  (stay_guests/)
-- Extra people staying on a reservation (besides the primary guest)
-- ------------------------------------------------------------
CREATE TABLE reservation_guests (
    stay_guest_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id INT UNSIGNED NOT NULL,
    first_name     VARCHAR(100) NOT NULL,
    last_name      VARCHAR(100) NOT NULL,
    age_group      ENUM('Adult','Child') NOT NULL DEFAULT 'Adult',
    relationship   VARCHAR(50)  NOT NULL DEFAULT '',
    phone          VARCHAR(30)  NOT NULL DEFAULT '',
    id_type        VARCHAR(50)  NOT NULL DEFAULT '',
    id_number      VARCHAR(100) NOT NULL DEFAULT '',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (stay_guest_id),
    KEY idx_rg_reservation (reservation_id),
    CONSTRAINT fk_rg_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations (reservation_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Optional starter data (delete this section if not needed)
-- ============================================================

INSERT INTO rooms (room_number, room_type, rate, room_status) VALUES
('101', 'Standard', 1500.00, 'Available'),
('102', 'Standard', 1500.00, 'Available'),
('103', 'Standard', 1500.00, 'Cleaning'),
('201', 'Deluxe',   2500.00, 'Available'),
('202', 'Deluxe',   2500.00, 'Available'),
('203', 'Deluxe',   2500.00, 'Maintenance'),
('301', 'Suite',    4500.00, 'Available'),
('302', 'Suite',    4500.00, 'Available');
