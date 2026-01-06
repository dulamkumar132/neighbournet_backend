# NeighbourNet Backend Setup Instructions

## Prerequisites
- XAMPP installed on Windows
- MySQL service running in XAMPP

## Step-by-Step Setup

### 1. Copy Backend Files
Copy all files from this `rwa_backend` folder to:
```
C:\xampp\htdocs\rwa_backend\
```

**Required files:**
- `db.php` - Database connection
- `login.php` - User login endpoint
- `register.php` - User registration endpoint
- `test_connection.php` - Database connection test
- `setup_database.sql` - Database schema

### 2. Start XAMPP Services
1. Open XAMPP Control Panel
2. Start **Apache** service (should show green "Running")
3. Start **MySQL** service (should show green "Running")

### 3. Create Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "Import" tab
3. Choose file: `setup_database.sql`
4. Click "Go" to execute

**OR manually:**
1. Click "New" to create database
2. Database name: `neighbournet_db`
3. Collation: `utf8mb4_general_ci`
4. Click "Create"
5. Copy and paste SQL from `setup_database.sql`

### 4. Test Backend
Visit these URLs to test:

**Database Connection:**
```
http://localhost/rwa_backend/test_connection.php
```
Expected response:
```json
{
  "status": "success",
  "message": "Database connection successful!",
  "database": "neighbournet_db",
  "tables_count": 3,
  "users_table_exists": true
}
```

**Registration Test (using Postman or curl):**
```bash
curl -X POST http://localhost/rwa_backend/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "Test User",
    "mobile_number": "1234567890",
    "email": "test@example.com",
    "flat_number": "A101",
    "password": "testpass123"
  }'
```

**Login Test:**
```bash
curl -X POST http://localhost/rwa_backend/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "mobile_number": "1234567890",
    "password": "testpass123"
  }'
```

## API Endpoints

### POST /register.php
Register a new user.

**Request:**
```json
{
  "full_name": "John Doe",
  "mobile_number": "1234567890",
  "email": "john@example.com",
  "flat_number": "A101",
  "password": "securepassword"
}
```

**Success Response:**
```json
{
  "status": "success",
  "message": "Registration successful",
  "data": {
    "user_id": 1,
    "message": "User registered successfully"
  }
}
```

### POST /login.php
Authenticate user login.

**Request:**
```json
{
  "mobile_number": "1234567890",
  "password": "securepassword"
}
```

**Success Response:**
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "user_id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "token": "dummy_token_1"
  }
}
```

## Troubleshooting

### Common Issues

**1. "Database connection failed"**
- Ensure MySQL is running in XAMPP
- Check database name is `neighbournet_db`
- Verify `db.php` has correct credentials

**2. "Table doesn't exist"**
- Run `setup_database.sql` in phpMyAdmin
- Check database was created properly

**3. "Method not allowed"**
- Ensure using POST requests for login/register
- Check Content-Type header is `application/json`

**4. "Invalid JSON data"**
- Verify request body is valid JSON
- Check all required fields are included

### File Locations
- **Windows XAMPP:** `C:\xampp\htdocs\rwa_backend\`
- **Database:** `neighbournet_db` in MySQL
- **Logs:** Check XAMPP error logs for PHP errors

## Security Notes
- Passwords are hashed using PHP's `password_hash()`
- Basic input validation is implemented
- CORS headers allow cross-origin requests
- Rate limiting on failed login attempts

## Database Schema
- **users:** User accounts and profiles
- **announcements:** Community announcements
- **chats:** Private messaging between users

All tables use UTF-8 encoding and include proper indexes for performance.
