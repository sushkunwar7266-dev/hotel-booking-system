# StayEase Hotel Booking System

A complete hotel room booking and management system built for academic purposes. This system allows customers to search for rooms, make bookings, and manage their reservations online, while hotel administrators can manage inventory, bookings, and customer information.

---

## Table of Contents

1. [What is StayEase?](#what-is-stayease)
2. [Who Can Use This System?](#who-can-use-this-system)
3. [Key Features](#key-features)
4. [How the System Works](#how-the-system-works)
5. [Installation Guide](#installation-guide)
6. [Default Login Credentials](#default-login-credentials)
7. [Technical Information](#technical-information)
8. [Security Features](#security-features)
9. [Project Structure](#project-structure)
10. [Troubleshooting](#troubleshooting)

---

## What is StayEase?

StayEase is a web-based hotel booking system that simplifies the process of reserving hotel rooms. Think of it like booking a room on any hotel website - customers can browse available rooms, check prices, make reservations, and pay online. The system also provides hotel staff with tools to manage rooms, track bookings, and monitor business performance.

---

## Who Can Use This System?

### For Hotel Guests (Customers)
- Browse available room types and their details
- Search for rooms based on check-in/check-out dates and number of guests
- View room prices, amenities, and photos
- Make online bookings
- Pay for reservations (demo payment for this version)
- View booking history
- Cancel bookings if needed

### For Hotel Staff (Administrators)
- View dashboard with statistics (total rooms, bookings, revenue, customers)
- Manage room types (add, edit, enable/disable, delete)
- Manage individual rooms (add, edit, change status, delete)
- View and manage all customer bookings
- Update booking statuses (pending, confirmed, checked-in, checked-out, cancelled)
- Monitor payment status
- Track recent booking activity

---

## Key Features

### Customer Features

**User Registration and Login**
- Customers can create their own accounts
- Secure password storage using encryption
- Login required to make bookings

**Room Search and Filtering**
- Search by check-in and check-out dates
- Filter by number of guests
- Filter by maximum price
- Filter by room type
- Real-time availability checking

**Booking Management**
- Easy booking process with clear pricing
- Automatic calculation of total cost (price per night multiplied by number of nights)
- View all your past and upcoming bookings
- Cancel bookings when needed
- Unique booking codes for each reservation

**Payment System**
- Demo payment implementation (ready for integration with real payment gateways)
- Payment status tracking
- Payment history

### Administrator Features

**Dashboard Overview**
- Total number of rooms in the hotel
- Total bookings count
- Number of registered customers
- Total revenue generated
- Recent booking activity

**Room Type Management**
- Create different categories of rooms (Deluxe, Executive, Suite, etc.)
- Add descriptions and images for each room type
- Enable or disable room types (hide from customers without deleting)
- View number of rooms in each category
- Edit or delete room types

**Room Inventory Management**
- Add individual rooms with room numbers
- Assign rooms to room types
- Set prices for each room
- Upload room photos and gallery images
- Add room descriptions and amenity lists
- Change room status (available, unavailable, maintenance)
- Edit or delete rooms
- Search and filter rooms by various criteria

**Booking Management**
- View all customer bookings
- Update booking status
- View customer information
- Check payment status
- Filter bookings by status, dates, or customer

---

## How the System Works

### For Customers (The Booking Journey)

**Step 1: Browse and Search**
- Visit the homepage
- See featured room types with images and descriptions
- Use the search form to enter your travel dates and number of guests

**Step 2: View Available Rooms**
- System shows only rooms that are:
  - Available for your selected dates
  - Can accommodate your number of guests
  - Not already booked by someone else

**Step 3: Select a Room**
- Click on a room to see full details
- View photos, amenities, capacity, and price
- Check availability for your dates

**Step 4: Make a Booking**
- Click "Book Now"
- Review booking details and total cost
- Confirm your reservation
- System generates a unique booking code

**Step 5: Payment**
- Proceed to payment page
- Complete demo payment (in production, this would be a real payment gateway)
- Receive booking confirmation

**Step 6: Manage Your Bookings**
- Visit "My Bookings" page
- View all your reservations
- Check booking status
- Cancel if plans change

### For Administrators (Managing the Hotel)

**Step 1: Login to Admin Panel**
- Use admin credentials to access the admin area
- See the dashboard with key statistics

**Step 2: Set Up Room Types**
- Create categories like "Deluxe Room", "Executive Suite", etc.
- Add descriptions that will appear on the homepage
- Upload representative images
- Enable/disable types as needed

**Step 3: Add Rooms to Inventory**
- Add individual rooms with unique room numbers
- Assign each room to a room type
- Set the price per night
- Upload photos
- List amenities (Wi-Fi, AC, TV, etc.)

**Step 4: Monitor Bookings**
- View all incoming bookings
- Confirm reservations
- Update status as guests check in/out
- Handle cancellations

**Step 5: Maintain Rooms**
- Mark rooms as "maintenance" when needed
- Update room information
- Adjust prices
- Add or remove rooms

---

## Installation Guide

### Prerequisites

You need to have installed on your computer:
- XAMPP, WAMP, Laragon, or any PHP development environment
- PHP version 8.0 or higher
- MySQL or MariaDB database server
- A web browser (Chrome, Firefox, Edge, etc.)

### Step-by-Step Installation

**Step 1: Download the Project**
- Extract the project files to your web server directory
  - For XAMPP: `C:\xampp\htdocs\hotel`
  - For WAMP: `C:\wamp\www\hotel`
  - For Laragon: `C:\laragon\www\hotel`

**Step 2: Start Your Servers**
- Open XAMPP/WAMP/Laragon Control Panel
- Start Apache (web server)
- Start MySQL (database server)

**Step 3: Create the Database**
- Open your browser and go to `http://localhost/phpmyadmin`
- Click on "New" to create a new database
- Or simply import the `database.sql` file:
  - Click "Import" tab
  - Choose the `database.sql` file from the project folder
  - Click "Go"
- The database will be created automatically with sample data

**Step 4: Configure Database Connection**
- Open the file `config/config.php` in a text editor
- Check these settings (default values should work for most setups):
  ```php
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'hotel_booking');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  ```
- If your MySQL has a password, update `DB_PASS` accordingly

**Step 5: Access the System**
- Open your browser
- Go to `http://localhost/hotel`
- You should see the StayEase homepage
- The system is now ready to use

**Step 6: Login as Administrator**
- Click "Login" in the navigation
- Use the default admin credentials (see below)
- Start managing your hotel

---

## Default Login Credentials

### Administrator Account
- **Email:** admin@stayease.test
- **Password:** password

**Important Security Note:** Change this password immediately after first login by creating a new admin user or updating the database.

### Test Customer Account
You can register a new customer account using any email address, or create one through the registration page.

---

## Technical Information

### Technology Stack

**Frontend**
- HTML5 for structure
- CSS3 for styling with custom properties (CSS variables)
- JavaScript for interactive features
- Font Awesome 6.5 for icons
- Responsive design that works on desktop, tablet, and mobile

**Backend**
- PHP 8+ (tested on PHP 8.0 and above)
- MySQL/MariaDB database
- PDO (PHP Data Objects) for database operations
- Prepared statements to prevent SQL injection
- Session-based authentication

**Security Implementation**
- Password hashing using PHP's `password_hash()` with bcrypt
- CSRF (Cross-Site Request Forgery) token protection
- SQL injection prevention through prepared statements
- XSS (Cross-Site Scripting) protection with output escaping
- Input validation and sanitization
- Secure session configuration
- Rate limiting on login attempts
- File upload validation and security

### Database Schema

**Tables Overview**

1. **users** - Stores customer and admin accounts
   - id, name, email, phone, password, role, created_at

2. **room_types** - Categories of rooms
   - id, name, description, image, capacity, amenities, status, created_at

3. **rooms** - Individual room inventory
   - id, room_number, room_type_id, capacity, price, image, gallery, description, amenities, status, created_at

4. **bookings** - Customer reservations
   - id, booking_code, user_id, room_id, check_in, check_out, guests, total_amount, status, payment_status, special_request, created_at

5. **payments** - Payment records
   - id, booking_id, transaction_id, amount, method, status, paid_at, created_at

**Database Triggers**

The system includes database triggers that automatically prevent double-booking:
- `before_booking_insert` - Validates new bookings
- `before_booking_update` - Validates booking updates

These triggers ensure that no room can be booked by two different customers for overlapping dates.

### Core Booking Logic

**How the System Prevents Double-Booking**

The system checks for date overlap using this logic:

A room is considered unavailable if there's an existing booking where:
- The existing booking is active (pending, confirmed, or checked-in status)
- AND the existing check-in date is before the requested check-out date
- AND the existing check-out date is after the requested check-in date

This prevents scenarios like:
- Customer A books Room 101 from June 1-5
- Customer B tries to book Room 101 from June 3-7 (overlaps, will be rejected)
- Customer C books Room 101 from June 6-10 (no overlap, allowed)

**Transaction Safety**

The system uses database transactions and row-level locking (SELECT FOR UPDATE) to prevent race conditions when multiple users try to book the same room simultaneously.

---

## Security Features

### Implemented Security Measures

**Authentication and Authorization**
- Secure session management with HttpOnly and SameSite cookies
- Role-based access control (customer vs admin)
- Session regeneration to prevent session fixation
- Automatic session timeout after 2 hours of inactivity
- Protected admin pages (cannot access without admin role)

**Input Validation and Sanitization**
- All user inputs are validated on both client and server side
- Email validation
- Date validation (prevents past dates, invalid date ranges)
- Numeric validation (prices, guest counts, etc.)
- File upload MIME type validation
- Path traversal protection for file uploads

**Attack Prevention**
- SQL injection prevention using PDO prepared statements
- XSS protection with output escaping function `e()`
- CSRF token validation on all forms
- Rate limiting on login (5 attempts per 15 minutes)
- Secure file upload handling
- Protection against IDOR (Insecure Direct Object Reference)
- Open redirect protection

**Error Handling**
- Custom error handler prevents stack trace exposure
- Errors logged to file instead of displayed to users
- Database connection errors hidden from end users
- Graceful error messages for users

**Security Headers**
- X-Content-Type-Options
- X-Frame-Options (clickjacking protection)
- X-XSS-Protection
- Content-Security-Policy
- Referrer-Policy
- Permissions-Policy

**Data Protection**
- Passwords hashed using bcrypt algorithm
- No sensitive data stored in plain text
- Booking ownership verified before operations
- Payment verification before processing

---

## Project Structure

```
hotel/
├── config/
│   └── config.php              # Database connection and global functions
├── admin/
│   ├── index.php               # Admin dashboard
│   ├── rooms.php               # Room inventory management
│   ├── room_types.php          # Room type management
│   ├── add_room.php            # Add/edit room form
│   ├── add_room_type.php       # Add/edit room type handler
│   └── bookings.php            # Booking management
├── api/
│   └── availability.php        # JSON API for room availability
├── assets/
│   ├── style.css               # Main stylesheet
│   └── app.js                  # JavaScript functionality
├── uploads/
│   ├── rooms/                  # Room images uploaded by admin
│   └── room_types/             # Room type images
├── logs/
│   └── php_errors.log          # Error log file
├── index.php                   # Homepage
├── rooms.php                   # Room search and listing
├── room.php                    # Individual room details
├── book.php                    # Booking form and processing
├── payment.php                 # Payment page
├── my_bookings.php             # Customer booking history
├── cancel.php                  # Booking cancellation
├── login.php                   # Login page
├── register.php                # Registration page
├── logout.php                  # Logout handler
├── partials_header.php         # Header template
├── partials_footer.php         # Footer template
├── database.sql                # Database schema and sample data
├── .htaccess                   # Apache configuration
└── README.md                   # This file
```

---

## Troubleshooting

### Common Issues and Solutions

**Issue: "Cannot connect to database"**
- Make sure MySQL is running in XAMPP/WAMP/Laragon
- Check if database name is correct in `config/config.php`
- Verify MySQL username and password
- Ensure the database was created from `database.sql`

**Issue: "Page not found" or blank page**
- Check if Apache is running
- Verify the project is in the correct directory (htdocs, www, etc.)
- Make sure you're using the correct URL (e.g., `http://localhost/hotel`)
- Check Apache error logs

**Issue: "Cannot upload images"**
- Ensure `uploads/rooms/` and `uploads/room_types/` folders exist
- Check folder permissions (should be writable)
- Verify PHP `upload_max_filesize` and `post_max_size` settings in php.ini

**Issue: "Session errors"**
- Clear browser cookies
- Check if PHP session folder is writable
- Restart Apache server

**Issue: Login not working**
- Verify you're using correct credentials
- Check if rate limiting is active (wait 15 minutes if locked)
- Clear browser cache and cookies
- Check database for user record

**Issue: Double-booking still occurs**
- Verify database triggers are created (check `database.sql` import)
- Check MySQL error log for trigger execution failures
- Ensure InnoDB engine is used (required for transactions)

**Issue: Images not displaying**
- Check if image files exist in `uploads/` folder
- Verify file permissions
- Check browser console for 404 errors
- Ensure image paths are correct in database

---

## Payment Integration Note

The current payment system is a **demo implementation** designed for testing and development purposes. In a production environment, you should integrate a real payment gateway such as:

**For Nepal:**
- eSewa
- Khalti
- IME Pay
- Connect IPS

**International:**
- Stripe
- PayPal
- Square

To integrate a real payment gateway:
1. Sign up with the payment provider
2. Obtain API credentials (merchant ID, secret keys, etc.)
3. Replace the demo payment logic in `payment.php`
4. Follow the payment provider's integration documentation
5. Test in sandbox/test mode before going live
6. Implement proper webhook handlers for payment notifications

---

## Future Enhancement Ideas

- Email notifications for bookings and confirmations
- SMS notifications
- Real payment gateway integration
- Multi-language support
- Advanced reporting and analytics
- Seasonal pricing
- Discount and promo code system
- Customer reviews and ratings
- Loyalty program
- Mobile app
- Calendar view for bookings
- Automatic check-in/check-out reminders
- Invoice generation and download
- Integration with property management systems

---

## Project Credits

**Developed by:** Academic BCA Project
**Purpose:** Hotel Management System for educational purposes
**Technology:** PHP, MySQL, HTML, CSS, JavaScript
**License:** Educational/Academic Use

---

## Support and Feedback

For issues, questions, or suggestions:
- Review this README file thoroughly
- Check the troubleshooting section
- Examine PHP error logs in `logs/php_errors.log`
- Check Apache and MySQL error logs
- Test with default credentials first
- Verify all installation steps were followed

---

## Final Notes

This system is production-ready in terms of code quality and security, but remember to:

1. Change default admin password immediately
2. Configure proper backup system for database
3. Set up SSL certificate (HTTPS) for production
4. Integrate real payment gateway
5. Configure email system for notifications
6. Set up proper hosting environment
7. Regular database backups
8. Monitor system logs
9. Keep PHP and MySQL updated
10. Test thoroughly before deployment

The system is designed to be easy to understand, maintain, and extend. The code follows best practices and includes comments where necessary. Feel free to customize and enhance it according to your specific requirements.

---

Thank you for using StayEase Hotel Booking System!
