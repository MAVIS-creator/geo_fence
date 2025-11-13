<h1 align="center">🌍 Geo-Fence Link Generator</h1>

<p align="center">
  <strong>Create location-restricted links that only work within specific GPS areas.</strong><br>
  Perfect for events, location-based content, treasure hunts, and geo-restricted access control.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-blue?logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License">
  <img src="https://img.shields.io/badge/Build-Stable-success" alt="Status">
  <img src="https://img.shields.io/badge/Security-JWT%20Protected-purple" alt="Security">
</p>

---

## 🚀 Features

### ⚙️ Core Functionality
- ✅ **Geo-Fenced Links** — Generate links restricted by GPS radius  
- ✅ **JWT Security** — Tamper-proof tokens with cryptographic signing  
- ✅ **QR Code Generation** — Auto-generate QR codes for easy sharing  
- ✅ **Real-time Analytics** — Track all access attempts  
- ✅ **Rate Limiting** — Configurable anti-spam shield  
- ✅ **Email Notifications** — Optional access alerts  
- ✅ **Admin Dashboard** — Manage links + analytics visually  
- ✅ **CSRF Protection** — Symfony-based form security  
- ✅ **Link Expiration** — Auto-expiry timer for each link  
- ✅ **Interactive Map** — Set geofence via drag-and-drop  
- ✅ **Multiple Coordinate Formats** — Decimal, DMS, Plus Codes (Full & Short)  

### 🧠 User Experience
- 🎨 Sleek **dark-theme UI** with smooth animations  
- 📱 Fully responsive layout (mobile, tablet, desktop)  
- 🗺️ Interactive **Leaflet.js** maps  
- 📋 One-click link copying  
- 🔒 Secure location verification flow  

---

## 📋 Requirements

| Component | Version / Info |
|------------|----------------|
| **PHP** | 8.0+ |
| **Composer** | Required |
| **Extensions** | `sodium`, `json`, `curl` |
| **Server** | Apache / Nginx / PHP built-in server |

## 🛠️ Installation

### 1. Clone or Download

```bash
git clone https://github.com/MAVIS-creator/geo_fence.git
cd geo_fence
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Enable PHP Sodium Extension

Edit your `php.ini` file and uncomment:
```ini
extension=sodium
```

### 4. Configure Environment

The `.env` file is already set up with defaults:

```env
APP_URL=http://localhost:8000
TIMEZONE=UTC
JWT_SECRET=your-secret-key
RATE_LIMIT_MAX=15
RATE_LIMIT_WINDOW=60
NOTIFICATION_EMAIL=  # Optional: your@email.com
```

### 5. Set Permissions

```bash
chmod -R 755 data/
```

### 6. Start Development Server

```bash
php -S localhost:8000 -t public
```

Visit: `http://localhost:8000`

## 📖 Usage Guide

### Creating a Geo-Fenced Link

1. **Navigate to** `http://localhost:8000/index.php`
2. **Set Location** - Click on the map or use "Use My Current Location"
3. **Set Radius** - Define the allowed area (5-2000 meters)
4. **Enter Target URL** - Where users will be redirected if inside the fence
5. **Set Expiry** - Choose when the link should expire
6. **Generate** - Get your shareable link and QR code!

### How It Works

1. **Admin creates link:**
   - Enters target URL, GPS coordinates, radius, and expiry
   - System generates JWT token with geo-fence data
   - Returns shareable link: `https://yourdomain.com/redirect.php?token=...`

2. **User clicks link:**
   - Browser requests location permission
   - System verifies JWT signature and expiration
   - Calculates distance using Haversine formula
   - **If inside fence** → Redirects to target URL ✅
   - **If outside fence** → Shows error with distance ❌

3. **Analytics tracked:**
   - Every access attempt is logged
   - Dashboard shows success/fail statistics
   - Optional email notifications sent

## 🏗️ Architecture

### Tech Stack

| Feature | Technology |
|---------|------------|
| Framework | Pure PHP (no framework) |
| Environment | `vlucas/phpdotenv` |
| Validation | `respect/validation` |
| Security | `symfony/security-csrf` |
| Tokens | `lcobucci/jwt` |
| Logging | `monolog/monolog` |
| Dates | `nesbot/carbon` |
| QR Codes | `endroid/qr-code` |
| UUIDs | `ramsey/uuid` |
| Plus Codes | `c3t4r4/openlocationcode` |

## 📍 Coordinate Format Support

The system supports multiple ways to input coordinates:

### 1. Decimal Degrees (Default)
```
8.165722, 4.265806
```

### 2. DMS (Degrees, Minutes, Seconds)
```
8°09'56.6"N 4°15'56.9"E
```

### 3. Plus Codes (Open Location Code)
```
6FRR5274+P6
```

**How to get a Plus Code:**
1. Open Google Maps
2. Long-press any location
3. Tap the coordinates at the bottom
4. Scroll down to find the Plus Code
5. Copy and paste into the generator!

All formats are automatically converted to decimal degrees internally. See [COORDINATE_FORMATS.md](COORDINATE_FORMATS.md) for detailed examples and usage instructions.

### File Structure

```
geo_fence/
├── public/
│   ├── index.php        # Link creation page
│   ├── dashboard.php    # Analytics dashboard
│   ├── redirect.php     # Geo-fence verification
│   └── assets/
│       └── style.css    # Modern UI styling
├── data/
│   ├── links.json       # Persistent link storage
│   ├── analytics.json   # Access tracking data
│   ├── rate_limits.json # Rate limiting state
│   └── app.log          # Application logs
├── bootstrap.php        # Core initialization
├── composer.json        # Dependencies
└── .env                 # Configuration
```

### Security Features

- **JWT Signing** - All geo-fence data is cryptographically signed
- **CSRF Protection** - Forms protected against cross-site attacks
- **Rate Limiting** - Configurable per-IP request throttling
- **Input Validation** - Strict lat/lng/radius validation
- **No Database** - JSON file storage (easily upgradable to DB)

## 🎯 Use Cases

- 🎪 **Event Access** - Links that only work at your event location
- 🏫 **Campus Gating** - Content only accessible on university grounds
- 🎁 **Treasure Hunts** - Location-based clue progression
- 🎬 **Geo-Marketing** - Promotional content for local visitors
- 📍 **Attendance** - Verify physical presence at a location
- 🔐 **Restricted Content** - Location-based content gates

## ⚙️ Configuration

### Rate Limiting

Edit `.env`:
```env
RATE_LIMIT_MAX=15      # Max attempts
RATE_LIMIT_WINDOW=60   # Time window in seconds
```

### Email Notifications

To enable email alerts on link access:
```env
NOTIFICATION_EMAIL=admin@example.com
```

**Note:** Uses PHP's `mail()` function. For production, configure SMTP.

### JWT Security

Generate a secure secret:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

Update `.env`:
```env
JWT_SECRET=your-generated-secret-here
```

## 📊 Dashboard Features

Access at `http://localhost:8000/dashboard.php`:

- 📈 Overall statistics (total attempts, success rate)
- 📋 List all generated links
- 🔍 Per-link analytics
- 🗑️ Delete expired/unwanted links
- 📱 Download QR codes
- 🔗 Quick link access

## 🔧 Customization

### Change UI Theme

Edit `public/assets/style.css` variables:

```css
:root {
  --bg-primary: #0a0e27;
  --accent-purple: #7c3aed;
  --accent-blue: #3b82f6;
  /* ... customize colors */
}
```

### Adjust Geo-Fence Limits

Edit `bootstrap.php`:

```php
function v_radius($x){ 
  return v::intVal()->between(5, 5000)->validate($x); 
}
```

## 🐛 Troubleshooting

### "SSL certificate problem" during composer install

**Solution:**
```bash
# Enable sodium extension
# Edit php.ini and uncomment: extension=sodium

# Then:
composer install --ignore-platform-reqs
```

### Location not working

- Ensure HTTPS (browsers require secure context for geolocation)
- Check browser location permissions
- Test on `localhost` (allowed without HTTPS)

### QR codes not generating

Check logs in `data/app.log` for errors. Fallback to external API is automatic.

## 📝 License

MIT License - Feel free to use in your projects!

## 🤝 Contributing

Contributions welcome! Feel free to:
- Report bugs
- Suggest features
- Submit pull requests

## 🙏 Credits

Built with ❤️ by MAVIS-creator

Uses these amazing open-source libraries:
- Leaflet.js for maps
- Composer packages (see composer.json)

---

**Note:** This is designed for development/demonstration. For production:
- Use a proper database (MySQL/PostgreSQL)
- Configure SMTP for email
- Enable HTTPS
- Add user authentication
- Implement backup strategy
