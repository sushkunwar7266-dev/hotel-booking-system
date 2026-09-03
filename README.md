# StayEase Hotel Booking System

Academic BCA project implementation based on the supplied Hotel Booking System proposal.

## Stack
- HTML/CSS/JavaScript-ready frontend
- PHP 8+
- MySQL/MariaDB
- PDO prepared statements
- Session authentication
- CSRF protection
- Responsive UI

## Included modules
- Customer registration/login/logout
- Room search by dates and guests
- Room type, price and amenity filtering
- Real-time availability checks against bookings
- Booking creation and date-overlap protection
- Automatic total calculation: price/night × nights
- Demo payment and payment records
- Booking history and cancellation
- Admin dashboard
- Room inventory management
- Booking status management
- JSON availability API

## Installation
1. Create a MySQL database by importing `database.sql`.
2. Edit `config/config.php` if your MySQL credentials differ.
3. Put the project folder in XAMPP `htdocs`, Laragon `www`, or a PHP hosting directory.
4. Start Apache/PHP and MySQL.
5. Open `http://localhost/hotel_booking_system/`.
6. Admin login:
   - Email: `admin@stayease.test`
   - Password: `password`

## Important
The payment page is deliberately a demo payment implementation because the proposal does not specify a real payment provider or merchant credentials. Replace `payment.php` with an eSewa/Khalti/card integration when production credentials and gateway requirements are available.

## Core booking logic
A room is unavailable when an existing pending/confirmed/checked-in booking overlaps the requested period:

existing check-in < requested check-out
AND
existing check-out > requested check-in

This prevents the common double-booking overlap case.
