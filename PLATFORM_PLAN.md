# رحلة - Rehla Platform Plan

## Project Overview
منصة رحلة مساعد - Platform for managing merchants and subscriptions with admin and customer dashboards.

---

## Core Requirements

### 1. User Interface (UI/UX)
- **Theme Colors:**
  - Primary Green: `#25D366` (bright green)
  - Secondary Green: `#128C7E` (dark green)
  - Background Dark: `#1A1D21` / `#111827`
  - Card Background: `rgba(30, 35, 40, 0.9)`
  - Text Light: `#FFFFFF`
  - Text Muted: `#9CA3AF`
  - Accent: `#25D366`

- **Design Guidelines:**
  - Arabic content (RTL support)
  - Mobile-first responsive design
  - Modern icons (NO emojis)
  - Use Heroicons or similar icon library
  - Rounded corners on cards and buttons
  - Glass-morphism effect on cards
  - Green gradient backgrounds following the login-bg.svg style

### 2. Responsive Design
- Mobile: 320px - 768px (PRIMARY focus)
- Tablet: 768px - 1024px
- Desktop: 1024px+
- All components must be fully responsive

---

## User Roles & Authentication

### Admin Role
- **Default Credentials:**
  - Username: `invnty`
  - Password: `2001587`
  
- **Capabilities:**
  - Manage all users/customers
  - Approve/Reject customer registrations
  - Create subscription packages (name, type, features)
  - Assign subscriptions to customers with expiry dates
  - View all statistics and reports
  - Manage stores and merchants

### Customer Role
- **Registration Flow:**
  1. Customer registers with required fields
  2. Status set to "pending" by default
  3. Customer sees "waiting for approval" message
  4. Admin approves in dashboard
  5. Customer can then access dashboard

- **Registration Fields:**
  - اسم التاجر (Merchant Name)
  - البريد الالكتروني (Email)
  - رقم الهاتف (Phone Number)
  - رقم الواتساب (WhatsApp Number)
  - الرقم السري (Password)
  - تأكيد الرقم السري (Confirm Password)
  - رابط الفيس بوك (Facebook Link)
  - رابط الانستجرام (Instagram Link)
  - عنوان المخزن (Store Address)
  - الباقة (Subscription Package)

- **Customer Status:**
  - `pending` - Waiting for admin approval
  - `approved` - Can access dashboard
  - `rejected` - Registration rejected
  - `suspended` - Account suspended

---

## Database Structure

### Users Table
```
- id
- name
- email
- phone
- whatsapp
- password
- role (admin/customer)
- status (pending/approved/rejected/suspended)
- facebook_link
- instagram_link
- store_address
- subscription_id
- subscription_expires_at
- created_at
- updated_at
```

### Subscriptions Table
```
- id
- name
- type
- description
- price
- duration_days
- features (JSON)
- is_active
- created_at
- updated_at
```

### Stores Table (Future)
```
- id
- user_id
- name
- address
- created_at
- updated_at
```

---

## Pages & Routes

### Public Routes
1. `/login` - Login page (shared for admin & customer)
2. `/register` - Customer registration page

### Admin Dashboard Routes (`/admin/*`)
1. `/admin/dashboard` - الرئيسية (Home/Statistics)
2. `/admin/merchants` - المتاجر (Merchants/Stores)
3. `/admin/subscriptions` - الباقات (Packages)
4. `/admin/pending-requests` - طلبات الانضمام (Pending Registrations)

### Customer Dashboard Routes (`/customer/*`)
1. `/customer/dashboard` - Customer home
2. `/customer/pending` - Waiting for approval page
3. `/customer/profile` - Profile settings

---

## Theme Assets
- Logo: `/images/logo.png`
- Background: `/images/login-bg.svg`

---

## Phase 1: Basic Setup (Current)
- [x] Create project plan document
- [ ] Setup authentication system
- [ ] Create login page
- [ ] Create registration page
- [ ] Create admin dashboard (basic)
- [ ] Create customer dashboard (basic)
- [ ] Create pending approval page

## Phase 2: Core Features
- [ ] Subscription management
- [ ] User approval workflow
- [ ] Statistics dashboard

## Phase 3: Advanced Features
- [ ] Store management
- [ ] Reports and analytics
- [ ] Notifications system

---

## Notes
- Always read this file before making changes
- Follow the theme exactly as shown in the images
- Mobile-first approach
- Arabic content only
- No emojis - use modern icons only
