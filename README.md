# 🔐 xsukax MD5 Generator

A privacy-first, single-file MD5 hash generator with SQLite tag storage, rate limiting, and sitemap generation. Built with PHP and vanilla JavaScript for optimal security and performance.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![SQLite](https://img.shields.io/badge/SQLite-3-green)](https://www.sqlite.org/)

**Live Demo:** [https://xsukax.ct.ws/md5/index.php](https://xsukax.ct.ws/md5/index.php)

## 📋 Overview

xsukax MD5 Generator is a lightweight, self-contained web application that generates MD5 hashes entirely client-side while offering optional server-side tag storage. The project emphasizes privacy, security, and user experience with a clean GitHub-inspired interface.

**Key Highlights:**
- **100% Client-Side Hashing:** All MD5 computation happens in the browser - your data never leaves your device
- **Zero External Dependencies:** Complete RFC 1321 MD5 implementation in vanilla JavaScript
- **Smart Rate Limiting:** IP-based protection (20 tags/minute) prevents abuse
- **SEO-Ready:** Built-in sitemap.xml generation for search engine optimization
- **Single-File Architecture:** One file deployment - just upload `index.php` and you're done

## ✨ Features

### Core Functionality
- **Client-Side MD5 Hashing** - RFC 1321 compliant implementation with UTF-8 support
- **Real-Time Hash Generation** - Instant hash updates as you type
- **Auto-Save System** - Optional automatic tag storage to SQLite database
- **Tag Cloud** - Visual tag browsing with randomized sizing
- **URL Parameter Support** - Direct hash generation via `?txt=yourtext`
- **NoSave Mode** - `?txt=text&nosave=1` for hash-only operations

### Security & Performance
- **Rate Limiting** - IP-based throttling (20 tags per 60 seconds)
- **SQLite Storage** - Lightweight, serverless database with automatic cleanup
- **Privacy-Focused** - No tracking, no external requests, no data collection
- **XSS Protection** - Proper input sanitization and output escaping
- **Cloudflare Compatible** - Supports CF-Connecting-IP header

### User Experience
- **GitHub-Style UI** - Clean, modern interface inspired by GitHub's design system
- **Responsive Design** - Mobile-friendly layout with touch optimizations
- **Copy to Clipboard** - One-click hash copying with fallback support
- **Visual Notifications** - Toast-style success/error messages
- **Character Counter** - Real-time input length tracking
- **Keyboard Shortcuts** - `Ctrl+Enter` to generate hash

### Developer Features
- **Sitemap Generation** - Automatic XML sitemap for all stored tags
- **Database Auto-Init** - Creates SQLite schema on first run with example data
- **AJAX API** - JSON endpoints for tag operations and stats
- **Clean Code** - Well-documented, PSR-compatible PHP structure

## 🎬 Demo

**Live Application:** [https://xsukax.ct.ws/md5/index.php](https://xsukax.ct.ws/md5/index.php)

### Example Usage

**Hash with Auto-Save:**
```
https://xsukax.ct.ws/md5/index.php?txt=password123
```

**Hash Without Saving:**
```
https://xsukax.ct.ws/md5/index.php?txt=password123&nosave=1
```

**Sitemap Access:**
```
https://xsukax.ct.ws/md5/index.php?sitemap
https://xsukax.ct.ws/md5/sitemap.xml
```

### Screenshots

**Main Interface:**
Clean input area with real-time hashing and stats display.

**Tag Cloud:**
Randomized tag visualization with varying sizes and quick-hash links.

**Rate Limit Protection:**
User-friendly error messages when limits are reached.

## 🔧 Prerequisites

### Server Requirements
- **PHP:** 7.4 or higher
- **PDO SQLite Extension:** Enabled (usually included by default)
- **Write Permissions:** Application directory must be writable for SQLite database creation

### Client Requirements
- **Modern Browser:** Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **JavaScript:** Enabled (required for MD5 generation)

### Optional Enhancements
- **HTTPS:** Recommended for production deployments
- **Cloudflare:** Automatic IP detection support
- **URL Rewriting:** For cleaner sitemap.xml URLs

## 📦 Installation

### Method 1: Direct Upload (Recommended)

1. **Download the file:**
   ```bash
   wget https://raw.githubusercontent.com/xsukax/xsukax-MD5-Generator/main/index.php
   ```

2. **Upload to your web server:**
   ```bash
   scp index.php user@yourserver.com:/var/www/html/md5/
   ```

3. **Set permissions:**
   ```bash
   chmod 755 index.php
   chmod 775 /var/www/html/md5/  # Directory must be writable
   ```

4. **Access in browser:**
   ```
   https://yourdomain.com/md5/index.php
   ```

### Method 2: Git Clone

1. **Clone the repository:**
   ```bash
   git clone https://github.com/xsukax/xsukax-MD5-Generator.git
   cd xsukax-MD5-Generator
   ```

2. **Move to web directory:**
   ```bash
   sudo cp index.php /var/www/html/md5/
   ```

3. **Set ownership (if needed):**
   ```bash
   sudo chown www-data:www-data /var/www/html/md5/
   ```

### Method 3: Docker (Alternative)

Create a `Dockerfile`:
```dockerfile
FROM php:8.1-apache
RUN docker-php-ext-install pdo pdo_sqlite
COPY index.php /var/www/html/
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
```

Build and run:
```bash
docker build -t md5-generator .
docker run -d -p 8080:80 -v $(pwd)/data:/var/www/html md5-generator
```

## ⚙️ Configuration

### Database Initialization

The application automatically creates `tags.db` on first run with:
- **Tags table:** Stores unique tags with timestamps
- **Rate limit table:** Tracks IP addresses and request times
- **Example data:** 20 pre-populated common tags

### URL Rewriting (Optional)

For cleaner sitemap URLs, add to `.htaccess`:
```apache
RewriteEngine On
RewriteRule ^sitemap\.xml$ index.php?sitemap [L]
```

### Rate Limiting Adjustment

Modify these values in `checkRateLimit()` function:
```php
$timeWindow = 60;      // Seconds (default: 60)
$maxRequests = 20;     // Max tags per window (default: 20)
```

### Tag Length Limit

Adjust maximum tag length in `insertTag()`:
```php
$tag = mb_substr(trim($text), 0, 30);  // Default: 30 characters
```

### Custom Example Tags

Replace the `$exampleTags` array in `initDatabase()`:
```php
$exampleTags = [
    'yourtag1',
    'yourtag2',
    // ... up to any number of tags
];
```

## 🚀 Usage

### Basic Hash Generation

1. **Enter text** in the input area
2. **View hash** automatically generated in real-time
3. **Click "Generate Hash"** to save (if auto-save enabled)
4. **Copy hash** using the "Copy Hash" button

### Auto-Save Toggle

- **Enabled (default):** Tags are saved to database when generated
- **Disabled:** Hashing only, no database storage
- Toggle using the checkbox above input area

### URL Parameters

**Direct hashing with save:**
```
?txt=yourtext
```

**Direct hashing without save:**
```
?txt=yourtext&nosave=1
```

**Access sitemap:**
```
?sitemap
```

### Tag Cloud Navigation

- Click any tag to instantly hash it
- Tag sizes randomized for visual variety
- Click refresh (↻) to reload with new random tags

### Keyboard Shortcuts

- `Ctrl + Enter` - Generate/save hash
- Standard text editing shortcuts supported

### API Endpoints

**Save tag (AJAX):**
```javascript
fetch('index.php', {
    method: 'POST',
    body: new FormData([
        ['action', 'save_tag'],
        ['text', 'yourtext']
    ])
})
```

**Get tag count:**
```javascript
fetch('index.php', {
    method: 'POST',
    body: new FormData([
        ['action', 'get_tag_count']
    ])
})
```

## 📁 Project Structure

```
xsukax-MD5-Generator/
│
├── index.php              # Single-file application (all code)
│   ├── PHP Backend
│   │   ├── Database Functions
│   │   │   ├── initDatabase()      # SQLite initialization
│   │   │   ├── getRandomTags()     # Tag retrieval
│   │   │   ├── insertTag()         # Tag insertion
│   │   │   ├── getTagCount()       # Statistics
│   │   │   └── generateSitemap()   # XML generation
│   │   ├── Security Functions
│   │   │   ├── getUserIP()         # IP detection
│   │   │   ├── checkRateLimit()    # Rate throttling
│   │   │   └── recordRateLimit()   # Usage tracking
│   │   └── Request Handling
│   │       ├── Sitemap routing
│   │       └── AJAX endpoints
│   ├── HTML Structure
│   │   ├── Header section
│   │   ├── Tag cloud display
│   │   ├── Input/output interface
│   │   └── Footer information
│   ├── CSS Styling
│   │   └── GitHub-inspired design system
│   └── JavaScript
│       ├── md5()                   # RFC 1321 implementation
│       ├── Event handlers
│       ├── AJAX functions
│       └── UI updates
│
├── tags.db                # Auto-generated SQLite database
│   ├── tags               # Tag storage table
│   └── rate_limit         # Rate limiting table
│
└── README.md              # This file
```

### Code Organization

**PHP Functions (Lines 1-300):**
- Database management
- Rate limiting logic
- Sitemap generation
- Request routing

**HTML Template (Lines 301-500):**
- Semantic markup
- Tag cloud rendering
- Form interface

**CSS Styles (Lines 40-200):**
- GitHub design tokens
- Responsive layouts
- Component styles

**JavaScript (Lines 501-end):**
- MD5 algorithm
- DOM manipulation
- AJAX communication
- User interactions

## 🧪 Testing

### Manual Testing Checklist

**Functionality:**
- [ ] Hash generation works without input
- [ ] Real-time hashing updates correctly
- [ ] Copy to clipboard functions
- [ ] Auto-save toggle persists state
- [ ] URL parameters load correctly
- [ ] Tag cloud links work
- [ ] Sitemap generates valid XML

**Security:**
- [ ] Rate limiting triggers at 20 tags/minute
- [ ] XSS attempts are sanitized
- [ ] SQL injection prevented (PDO)
- [ ] Long inputs truncated properly

**Browser Compatibility:**
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers (iOS/Android)

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

### Getting Started

1. **Fork the repository**
   ```bash
   git clone https://github.com/yourusername/xsukax-MD5-Generator.git
   cd xsukax-MD5-Generator
   ```

2. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make your changes**
   - Follow existing code style
   - Add comments for complex logic
   - Test thoroughly

4. **Commit with clear messages**
   ```bash
   git commit -m "Add: Feature description"
   ```

5. **Push and create PR**
   ```bash
   git push origin feature/your-feature-name
   ```

### Code Standards

**PHP:**
- PSR-12 coding style
- Type hints where applicable
- DocBlocks for all functions
- Error handling with try-catch

**JavaScript:**
- ES6+ syntax preferred
- Consistent indentation (4 spaces)
- Descriptive variable names
- JSDoc comments for functions

**HTML/CSS:**
- Semantic HTML5
- BEM naming for CSS classes (when applicable)
- Responsive design patterns
- Accessibility compliance (WCAG 2.1)

### Pull Request Process

1. Update README.md with any new features
2. Ensure all tests pass
3. Update version number if applicable
4. Request review from maintainers
5. Address feedback promptly

### Issue Reporting

**Bug reports should include:**
- PHP version and server environment
- Browser version and OS
- Steps to reproduce
- Expected vs actual behavior
- Error messages or logs

**Feature requests should include:**
- Use case description
- Proposed solution
- Alternative approaches considered
- Potential impact on existing features

## 🔒 Security

### Security Features

**Implemented:**
- ✅ Rate limiting (DoS prevention)
- ✅ PDO prepared statements (SQL injection prevention)
- ✅ Input sanitization (XSS prevention)
- ✅ Client-side processing (data privacy)
- ✅ No external dependencies (supply chain security)

**Best Practices:**
- Keep PHP updated (7.4+ security patches)
- Use HTTPS in production
- Regular database backups
- Monitor rate limit logs
- Implement CSP headers (optional)

### Known Limitations

- **MD5 is not cryptographically secure** - Use for non-security purposes only
- **Rate limiting is IP-based** - VPN users share limits
- **No CAPTCHA** - Automated abuse possible within limits
- **SQLite limitations** - Not suitable for very high traffic

## 📄 License

This project is licensed under the **GNU General Public License v3.0**.

**Full license:** [LICENSE](https://www.gnu.org/licenses/gpl-3.0.en.html)

**Permissions:**
- ✅ Modification
- ✅ Distribution
- ✅ Patent use
- ✅ Private use

**Conditions:**
- 📋 License and copyright notice
- 📋 State changes
- 📋 Disclose source
- 📋 Same license

**Limitations:**
- ❌ Liability
- ❌ Warranty

## 👨‍💻 Author / Maintainers

### Primary Developer

**xsukax**
- GitHub: [@xsukax](https://github.com/xsukax)
- Website: [Tech Me Away !!!](https://xsukax.com/)

## 📞 Contact / Support

### Get Help

**GitHub Issues:** [Create an issue](https://github.com/xsukax/xsukax-MD5-Generator/issues)
- Bug reports
- Feature requests
- General questions
- Security vulnerabilities
- Partnership inquiries
- Custom development requests


**Website:** [https://xsukax.com/](https://xsukax.com/)
- More projects
- Tutorials
- Blog posts

### FAQ

**Q: Is MD5 secure for passwords?**
A: No! MD5 is cryptographically broken. This tool is for checksums, file verification, and non-security hashing only.

**Q: Can I increase the rate limit?**
A: Yes, modify `$maxRequests` in the `checkRateLimit()` function.

**Q: Does this work without internet?**
A: Yes! Download the file and run it locally with PHP. Hashing is 100% offline.

**Q: Why SQLite instead of MySQL?**
A: Simplicity and portability. Single-file deployment with zero configuration.

**Q: Can I self-host this?**
A: Absolutely! That's the whole point. Just upload `index.php` and go.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/xsukax">xsukax</a><br>
  <sub>Privacy-focused • Security-first • Self-hosted</sub>
</p>

<p align="center">
  <a href="https://github.com/xsukax/xsukax-MD5-Generator/stargazers">⭐ Star this repo</a> •
  <a href="https://github.com/xsukax/xsukax-MD5-Generator/fork">🍴 Fork it</a> •
  <a href="https://github.com/xsukax/xsukax-MD5-Generator/issues">🐛 Report bug</a>
</p>
