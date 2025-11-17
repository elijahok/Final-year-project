# Smart E-Procurement System

A comprehensive e-procurement platform designed for agricultural supply chains, connecting farmers, transporters, vendors, and administrators in a seamless digital marketplace.

## Features

### Core Functionality
- **User Management**: Multi-role system (Admin, Farmer, Transporter, Vendor)
- **Tender Management**: Create, bid on, and manage procurement tenders
- **Transport Management**: Request, track, and manage transportation
- **Payment System**: Mobile money integration (M-Pesa, Airtel Money)
- **Quality Reporting**: Track and manage quality issues
- **Analytics Dashboard**: Comprehensive reporting and insights
- **Notifications**: Real-time alerts and updates

### Advanced Features
- **GPS Tracking**: Real-time location tracking for transport
- **Route Optimization**: Smart route planning for transporters
- **Wallet System**: Digital wallet for payments and transactions
- **Two-Factor Authentication**: Enhanced security with 2FA
- **Email Verification**: Account verification system
- **Password Reset**: Secure password recovery
- **Data Export**: Export reports in CSV, JSON, PDF formats

## Technology Stack

- **Backend**: PHP 8+
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Charts**: Chart.js
- **Icons**: Font Awesome
- **Authentication**: JWT-style session management

## Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL/MariaDB
- Apache/Nginx web server
- Composer (for PHP dependencies)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/smart-eprocurement-system.git
   cd smart-eprocurement-system
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure database**
   - Create a new database
   - Import the database schema from `database/schema.sql`
   - Update database credentials in `config/config.php`

4. **Configure application**
   - Copy `config/config.example.php` to `config/config.php`
   - Update the configuration values:
     - Database credentials
     - Base URL
     - Email settings
     - Mobile money API keys

5. **Set file permissions**
   ```bash
   chmod -R 755 .
   chmod -R 777 uploads/
   chmod -R 777 logs/
   ```

6. **Access the application**
   - Open your browser and navigate to `http://localhost/smart-eprocurement-system/public`

## Configuration

### Database Configuration
Update the following in `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
```

### Email Configuration
Configure SMTP settings for email notifications:
```php
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@example.com');
define('SMTP_PASSWORD', 'your_email_password');
```

### Mobile Money Configuration
Set up API keys for mobile money providers:
```php
define('MPESA_CONSUMER_KEY', 'your_mpesa_consumer_key');
define('MPESA_CONSUMER_SECRET', 'your_mpesa_consumer_secret');
define('AIRTEL_CLIENT_ID', 'your_airtel_client_id');
define('AIRTEL_CLIENT_SECRET', 'your_airtel_client_secret');
```

## User Roles

### Admin
- Full system access
- User management
- Tender approval
- System configuration
- Analytics and reporting

### Farmer
- Create transport requests
- Track shipments
- Submit quality reports
- Manage wallet

### Transporter
- Accept transport requests
- GPS tracking
- Route optimization
- Manage availability

### Vendor
- Submit bids for tenders
- Manage bids
- View awarded contracts
- Payment management

## API Documentation

### Authentication
All API endpoints require authentication. Include session cookies or API tokens in requests.

### Main API Endpoints

#### Analytics
- `GET /api/analytics.php?action=dashboard` - Get dashboard data
- `GET /api/analytics.php?action=stats&type=overview` - Get statistics
- `GET /api/analytics.php?action=trends&type=daily` - Get trend data
- `GET /api/analytics.php?action=export` - Export data

#### Quality Reports
- `GET /api/quality-reports.php` - List reports
- `POST /api/quality-reports.php?action=create` - Create report
- `PUT /api/quality-reports.php?action=respond` - Respond to report

#### Notifications
- `GET /api/notifications.php` - List notifications
- `PUT /api/notifications.php?action=mark_read` - Mark as read
- `POST /api/notifications.php?action=send` - Send notification

#### Mobile Money
- `POST /api/mobile-money.php?action=initiate_payment` - Initiate payment
- `GET /api/mobile-money.php?action=check_status` - Check payment status
- `GET /api/mobile-money.php?action=wallet_balance` - Get wallet balance

#### GPS Tracking
- `POST /api/gps-tracking.php?action=update_location` - Update location
- `GET /api/gps-tracking.php?action=get_locations` - Get locations
- `GET /api/route-optimization.php?action=optimize` - Optimize route

## Security Features

- CSRF protection on all forms
- SQL injection prevention
- XSS protection
- Input validation and sanitization
- Password hashing (bcrypt)
- Session management
- Two-factor authentication
- Rate limiting
- Activity logging

## Testing

### Running Tests
```bash
# Run PHPUnit tests
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html tests/coverage
```

### Test Coverage
- Unit tests for core functions
- Integration tests for API endpoints
- Security tests for authentication
- Performance tests for heavy operations

## Deployment

### Production Deployment

1. **Environment Setup**
   - Set up production server
   - Configure SSL certificate
   - Set up reverse proxy (if needed)

2. **Database Setup**
   - Create production database
   - Import schema
   - Set up backups

3. **Application Configuration**
   - Update `config/config.php` with production settings
   - Set up environment variables
   - Configure logging

4. **Security Hardening**
   - Disable debug mode
   - Set proper file permissions
   - Configure firewall
   - Set up monitoring

### Docker Deployment
```bash
# Build Docker image
docker build -t smart-eprocurement .

# Run container
docker run -p 80:80 smart-eprocurement
```

## Monitoring and Logging

### Logging
- Application logs: `logs/app.log`
- Error logs: `logs/error.log`
- Access logs: `logs/access.log`
- Security logs: `logs/security.log`

### Monitoring
- System performance metrics
- Database query monitoring
- API response times
- Error rate tracking

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## Support

For support and questions:
- Email: support@example.com
- Documentation: [Link to documentation]
- Issues: [GitHub Issues]

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Changelog

### Version 1.0.0
- Initial release
- Core e-procurement functionality
- User management system
- Tender and bid management
- Transport management
- Payment integration
- Quality reporting
- Analytics dashboard
- Mobile money support
- GPS tracking
- Route optimization

## Future Enhancements

- Mobile app development
- Advanced analytics with AI
- Blockchain integration for transparency
- Multi-language support
- Advanced reporting features
- Integration with ERP systems
- API rate limiting and quotas
- Advanced security features
