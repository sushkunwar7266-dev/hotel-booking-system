CREATE DATABASE IF NOT EXISTS hotel_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_booking;

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS room_types;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password VARCHAR(255) NOT NULL,
    role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE room_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    capacity INT UNSIGNED NOT NULL DEFAULT 2,
    amenities VARCHAR(500),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(30) NOT NULL UNIQUE,
    room_type_id INT UNSIGNED NOT NULL,
    capacity INT UNSIGNED DEFAULT 2,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    gallery TEXT,
    description TEXT,
    amenities VARCHAR(500),
    status ENUM('available','unavailable','maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rooms_type FOREIGN KEY (room_type_id) REFERENCES room_types(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_code VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    guests INT UNSIGNED NOT NULL DEFAULT 1,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','cancelled','checked_in','checked_out') DEFAULT 'pending',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    special_request TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_booking_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    INDEX idx_booking_dates (room_id, check_in, check_out, status),
    INDEX idx_booking_status (status, room_id, check_in, check_out),
    CONSTRAINT chk_dates CHECK (check_out > check_in)
) ENGINE=InnoDB;

-- Trigger to prevent overlapping bookings at database level
DELIMITER $$

CREATE TRIGGER before_booking_insert
BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    DECLARE overlap_count INT;
    DECLARE room_status VARCHAR(20);
    
    -- Check room status
    SELECT status INTO room_status FROM rooms WHERE id = NEW.room_id;
    
    IF room_status != 'available' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Room is not available for booking';
    END IF;
    
    -- Check for overlapping bookings
    IF NEW.status IN ('pending', 'confirmed', 'checked_in') THEN
        SELECT COUNT(*) INTO overlap_count
        FROM bookings
        WHERE room_id = NEW.room_id
          AND status IN ('pending', 'confirmed', 'checked_in')
          AND check_in < NEW.check_out
          AND check_out > NEW.check_in;
        
        IF overlap_count > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Booking dates overlap with existing booking';
        END IF;
    END IF;
END$$

CREATE TRIGGER before_booking_update
BEFORE UPDATE ON bookings
FOR EACH ROW
BEGIN
    DECLARE overlap_count INT;
    DECLARE room_status VARCHAR(20);
    
    -- Only check if dates or status changed to active status
    IF (NEW.check_in != OLD.check_in OR NEW.check_out != OLD.check_out OR NEW.status != OLD.status OR NEW.room_id != OLD.room_id) THEN
        
        -- Check room status
        SELECT status INTO room_status FROM rooms WHERE id = NEW.room_id;
        
        IF room_status != 'available' AND NEW.status IN ('pending', 'confirmed', 'checked_in') THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Room is not available for booking';
        END IF;
        
        -- Check for overlapping bookings (excluding current booking)
        IF NEW.status IN ('pending', 'confirmed', 'checked_in') THEN
            SELECT COUNT(*) INTO overlap_count
            FROM bookings
            WHERE room_id = NEW.room_id
              AND id != NEW.id
              AND status IN ('pending', 'confirmed', 'checked_in')
              AND check_in < NEW.check_out
              AND check_out > NEW.check_in;
            
            IF overlap_count > 0 THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Booking dates overlap with existing booking';
            END IF;
        END IF;
    END IF;
END$$

DELIMITER ;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    transaction_id VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('demo','cash','esewa','khalti','card') DEFAULT 'demo',
    status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (name,email,phone,password,role) VALUES
('System Administrator','admin@stayease.test','9800000000',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4JQ5QxV3V7K2x7xj6oVqV8oKp9u3f5W', 'admin');
-- Password for the demo admin above: password

INSERT INTO room_types (name,description,capacity,amenities) VALUES
('Deluxe Room','Comfortable room with modern amenities and a city view.',2,'Wi-Fi, AC, TV, Breakfast'),
('Executive Room','Spacious room suitable for business and leisure stays.',3,'Wi-Fi, AC, TV, Work Desk, Breakfast'),
('Family Suite','Large suite designed for families and groups.',5,'Wi-Fi, AC, TV, Living Area, Breakfast');

INSERT INTO rooms (room_number,room_type_id,price,image) VALUES
('101',1,4500,'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=1000'),
('102',1,4500,'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=1000'),
('201',2,6500,'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=1000'),
('202',2,6500,'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1000'),
('301',3,9000,'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=1000');
