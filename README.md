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

---

## 🛠️ Installation

### 1️⃣ Clone the Repository
```bash
git clone https://github.com/MAVIS-creator/geo_fence.git
cd geo_fence
```

### 2️⃣ Install Dependencies
```bash
composer install
```

### 3️⃣ Enable Sodium Extension
In your `php.ini`:

```ini
extension=sodium
```

### 4️⃣ Configure Environment
Edit your `.env`:

```env
APP_URL=http://localhost:8000
TIMEZONE=UTC
JWT_SECRET=your-secret-key
RATE_LIMIT_MAX=15
RATE_LIMIT_WINDOW=60
NOTIFICATION_EMAIL= # optional
```

### 5️⃣ Set Permissions
```bash
chmod -R 755 data/
```

### 6️⃣ Start the Dev Server
```bash
php -S localhost:8000 -t public
```

👉 **Visit:** `http://localhost:8000`

---

## 🧭 Usage Guide

### ✨ Create a Geo-Fenced Link

1. Go to `http://localhost:8000/index.php`
2. Click **Use My Location** or pick manually on map
3. Set **radius** (5-2000m)
4. Enter **target URL**
5. Set **expiry time**
6. Hit **Generate** → You'll get a shareable link + QR code 🎉

### 🧩 How It Works

1. **Admin creates link** → system generates JWT token
2. **User opens link** → browser requests location
3. **Backend verifies:**
   - ✅ Inside geofence → redirect
   - ❌ Outside → blocked with distance message
4. **All access attempts logged** → view analytics in dashboard

---

## 🏗️ Architecture Overview

### Tech Stack

| Feature | Technology |
|---------|------------|
| Framework | Native PHP |
| Env Config | `vlucas/phpdotenv` |
| Validation | `respect/validation` |
| Security | `symfony/security-csrf` |
| Tokens | `lcobucci/jwt` |
| Logging | `monolog/monolog` |
| Dates | `nesbot/carbon` |
| QR Codes | `endroid/qr-code` |
| UUIDs | `ramsey/uuid` |
| Plus Codes | `c3t4r4/openlocationcode` |

---

## 📍 Coordinate Formats Supported

| Format | Example |
|--------|---------|
| **Decimal Degrees** | `8.165722, 4.265806` |
| **DMS** | `8°09'56.6"N 4°15'56.9"E` |
| **Plus Codes (Full)** | `6FRR5274+P6` |
| **Plus Codes (Short)** | `5274+P6` (needs reference) |

📘 **Tip:** Get Plus Codes from Google Maps → Long press → Copy code.

📄 **Details:** See [COORDINATE_FORMATS.md](COORDINATE_FORMATS.md) and [SHORT_PLUS_CODE_SUPPORT.md](SHORT_PLUS_CODE_SUPPORT.md)

---

## 🗂️ File Structure

```
geo_fence/
├── public/
│   ├── index.php              # Link creation page
│   ├── dashboard.php          # Analytics dashboard
│   ├── redirect.php           # Geo-fence verification
│   ├── convert_coords.php     # Coordinate conversion API
│   ├── coordinate_help.html   # Coordinate format guide
│   ├── test_api.html          # API testing interface
│   └── assets/
│       ├── style.css          # UI styling
│       └── mavis.jpg          # Branding logo
├── data/
│   ├── links.json             # Stored links
│   ├── analytics.json         # Access logs
│   ├── rate_limits.json       # Rate limiter state
│   └── app.log                # Application logs
├── tools/
│   ├── test_coords.php        # Coordinate conversion tests
│   └── test_link.php          # Link generation tests
├── vendor/                    # Composer dependencies
├── bootstrap.php              # Core initialization
├── composer.json              # Dependency definitions
├── .env                       # Environment configuration
├── README.md                  # This file
├── COORDINATE_FORMATS.md      # Coordinate format guide
├── SHORT_PLUS_CODE_SUPPORT.md # Short Plus Code documentation
└── IMPLEMENTATION_SUMMARY.md  # Technical implementation details
```

---

## 🧱 Security Highlights

- 🔐 **JWT Signing** — Tamper-proof tokens
- 🧩 **CSRF Protection** — Secure forms
- 🚫 **Rate Limiting** — Prevent abuse
- 🧾 **Strict Validation** — Inputs verified
- 💾 **No Database** — JSON-based lightweight storage

---

## 🎯 Real-World Use Cases

- 🎪 **Event access control**
- 🏫 **Campus-based content**
- 🎁 **Treasure hunts**
- 🎬 **Localized marketing**
- 📍 **Attendance tracking**
- 🔐 **Restricted document access**

---

## ⚙️ Configuration Tweaks

### ⏱ Rate Limiting

```env
RATE_LIMIT_MAX=15
RATE_LIMIT_WINDOW=60
```

### 📧 Email Alerts

```env
NOTIFICATION_EMAIL=admin@example.com
```

### 🔑 JWT Secret

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Then add:

```env
JWT_SECRET=your-generated-secret
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
