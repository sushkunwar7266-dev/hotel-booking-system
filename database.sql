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
    capacity INT UNSIGNED NOT NULL DEFAULT 2,
    amenities VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(30) NOT NULL UNIQUE,
    room_type_id INT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    status ENUM('available','maintenance') DEFAULT 'available',
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
    special_request TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_booking_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    INDEX idx_booking_dates (room_id, check_in, check_out, status)
) ENGINE=InnoDB;

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
