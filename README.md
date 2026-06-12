# Rehla AI - Smart Sales Platform

Laravel-based e-commerce platform with **InvenGPT AI chatbot** for automated customer service on Facebook and Instagram.

---

## Features

### 🤖 AI-Powered Chat
- **InvenGPT API v3** integration
- Session-based conversations (1 lead = 1 session)
- SQLite caching for instant responses
- Intent detection (greeting, order, complaint, etc.)
- Auto escalation to human agent when needed
- Full Iraqi Arabic support

### 💬 Social Media Integration
- Facebook Messenger
- Instagram Direct Messages
- Webhook support for real-time messaging

### 📦 Inventory Management
- Products & Categories
- Stock tracking
- Multi-currency support (IQD, USD)
- Product images

### 📊 Sales & Orders
- Online orders from chat
- POS system
- Order tracking
- Reports & analytics

### 👥 Customer Management (Leads)
- Auto-create from chat conversations
- Customer info extraction (name, phone, address)
- Order history
- Interest tracking

### 🔐 User Roles
- **Admin**: Manage merchants, subscriptions
- **Customer (Merchant)**: Manage store, products, leads

---

## Quick Start

### 1. Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

### 2. Configure InvenGPT

Add to `.env`:

```env
INVENGPT_API_URL=http://127.0.0.1:5000
INVENGPT_TIMEOUT=60
APP_URL=http://127.0.0.1:8001
```

Start InvenGPT server:
```bash
# In InvenGPT directory
python app.py
```

### 3. Configure Facebook/Instagram

```env
FACEBOOK_CLIENT_ID=your_app_id
FACEBOOK_CLIENT_SECRET=your_app_secret
META_WEBHOOK_VERIFY_TOKEN=your_verify_token
```

### 4. Run Application

```bash
php artisan serve
```

Visit: `http://127.0.0.1:8001`

---

## Documentation

- **[InvenGPT Integration Guide](INVENGPT_INTEGRATION.md)** - Full API v3 documentation
- **[InvenGPT Summary](INVENGPT_SUMMARY.md)** - Quick reference
- **[Platform Plan](PLATFORM_PLAN.md)** - Features & roadmap
- **[Meta Setup Guide](META_SETUP_GUIDE.md)** - Facebook/Instagram configuration
- **[Messaging Setup](MESSAGING_SETUP.md)** - Webhooks setup

---

## API Endpoints

All endpoints are public (no auth required):

```http
GET /api/v1/products/{id}      # Product details
GET /api/v1/products            # List products
GET /api/v1/stores/{id}         # Store details
GET /api/v1/stores              # List stores
GET /api/v1/leads/{id}          # Customer/lead details
GET /api/v1/leads?store_id={id} # List customers
```

---

## Tech Stack

- **Backend**: Laravel 12
- **Database**: SQLite
- **AI**: InvenGPT API v3 (Python/Flask)
- **Frontend**: Blade Templates + Vanilla JS
- **Messaging**: Facebook Graph API
- **Cache**: Database/Redis

---

## Project Structure

```
app/
├── Http/Controllers/
│   ├── Api/              # Public API endpoints
│   ├── Customer/         # Merchant dashboard
│   └── Admin/            # Admin panel
├── Models/               # Eloquent models
├── Services/
│   ├── InvenGptService   # InvenGPT AI integration
│   └── AiChatService     # Chat processing
resources/
├── views/
│   ├── customer/         # Merchant UI
│   └── admin/            # Admin UI
routes/
├── web.php               # Web routes
├── api.php               # API routes
└── channels.php          # Broadcasting
```

---

## Default Users

**Admin:**
- Username: `invnty`
- Password: `2001587`

**Test Merchant:**
- Create via registration or admin panel

---

## Testing

### Test AI Connection

1. Login as merchant
2. Go to `/customer/ai-settings`
3. Click "اختبار" button
4. Should show: ✓ الاتصال ناجح - InvenGPT API v3

### Test Chat Flow

```bash
curl -X POST http://127.0.0.1:5000/api/v3/session/create \
  -H "Content-Type: application/json" \
  -d '{"store_id":"1","lead_id":"1","store_context":{"name":"Test"},"lead_info":{}}'
```

---

## Troubleshooting

### InvenGPT Connection Failed

```bash
# Check if server is running
curl http://127.0.0.1:5000/health

# Expected response:
# {"status": "healthy", "model_loaded": true}
```

### Session Not Found

- Sessions expire after 24 hours
- Laravel auto-creates new session

### Check Logs

```bash
tail -f storage/logs/laravel.log | grep InvenGPT
```

---In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
