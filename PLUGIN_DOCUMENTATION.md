# Kritim Solar Core Plugin - Complete Documentation

## 📋 Table of Contents
1. [Plugin Overview](#plugin-overview)
2. [Database Schema](#database-schema)
3. [User Roles](#user-roles)
4. [Project Workflow](#project-workflow)
5. [Meta Fields Reference](#meta-fields-reference)
6. [Complete File Structure](#complete-file-structure)
7. [API Endpoints](#api-endpoints)
8. [Notifications System](#notifications-system)
9. [Payment Integration](#payment-integration)
10. [Security Implementation](#security-implementation)

---

## 🎯 Plugin Overview

**Plugin Name:** Kritim Solar Core  
**Purpose:** Comprehensive solar project management system with vendor marketplace, bidding, and workflow tracking.

### Key Features:
- ✅ Multi-role user system (Admin, Manager, Area Manager, Vendor, Client)
- ✅ Project lifecycle management
- ✅ Vendor marketplace with bidding system
- ✅ Coverage area-based vendor assignments
- ✅ Step-by-step project progress tracking
- ✅ Payment integration (Razorpay)
- ✅ Real-time notifications (In-app, Email, WhatsApp)
- ✅ Comprehensive dashboards for each role

---

## 🗄️ Database Schema

### Custom Tables

#### 1. `wp_project_bids`
Stores all vendor bids for projects

| Column | Type | Description |
|--------|------|-------------|
| `id` | MEDIUMINT(9) | Primary key |
| `project_id` | BIGINT(20) | Reference to solar_project post |
| `vendor_id` | BIGINT(20) | Reference to user (vendor) |
| `bid_amount` | DECIMAL(10,2) | Bid amount in INR |
| `bid_type` | VARCHAR(10) | 'open' or 'hidden' |
| `bid_details` | TEXT | Vendor's bid description |
| `created_at` | DATETIME | Timestamp |

---

#### 2. `wp_solar_vendor_payments`
Tracks vendor registration and coverage payments

| Column | Type | Description |
|--------|------|-------------|
| `id` | MEDIUMINT(9) | Primary key |
| `vendor_id` | BIGINT(20) | Reference to user (vendor) |
| `razorpay_payment_id` | VARCHAR(255) | Razorpay payment ID |
| `razorpay_order_id` | VARCHAR(255) | Razorpay order ID |
| `amount` | DECIMAL(10,2) | Payment amount |
| `states_purchased` | TEXT | JSON array of states |
| `cities_purchased` | TEXT | JSON array of cities |
| `payment_status` | VARCHAR(50) | 'pending'/'completed' |
| `payment_date` | DATETIME | Timestamp |

---

#### 3. `wp_solar_process_steps`
Tracks vendor submission steps for each project

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT(20) | Primary key |
| `project_id` | BIGINT(20) | Reference to solar_project |
| `step_number` | INT(11) | Step sequence number |
| `step_name` | VARCHAR(200) | Step title |
| `image_url` | VARCHAR(500) | Uploaded proof image |
| `vendor_comment` | TEXT | Vendor notes |
| `client_comment` | TEXT | Client feedback |
| `admin_comment` | TEXT | Admin/Manager review |
| `admin_status` | VARCHAR(20) | 'pending'/'approved'/'rejected' |
| `approved_date` | DATETIME | Approval timestamp |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

---

#### 4. `wp_solar_notifications`
In-app notification system

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT(20) | Primary key |
| `user_id` | BIGINT(20) | Recipient user ID |
| `project_id` | BIGINT(20) | Related project (nullable) |
| `message` | TEXT | Notification message |
| `type` | VARCHAR(50) | Notification type |
| `status` | VARCHAR(20) | 'unread'/'dismissed' |
| `sent_email` | TINYINT(1) | Email sent flag |
| `sent_whatsapp` | TINYINT(1) | WhatsApp sent flag |
| `created_at` | DATETIME | Timestamp |

---

## 👥 User Roles

### 1. **Administrator**
- **Capability:** `manage_options`
- **Access:** Full system access, all dashboards
- **Permissions:**
  - Create/edit/delete all projects
  - Approve vendors
  - Review all submissions
  - Award bids
  - Access all analytics

### 2. **Manager**
- **Custom Role:** Similar to admin but limited scope
- **Access:** Team analysis, project reviews
- **Permissions:**
  - Review vendor submissions
  - Approve/reject steps
  - View analytics

### 3. **Area Manager**
- **Custom Role:** `area_manager`
- **Access:** Area Manager Dashboard
- **Permissions:**
  - Create projects in assigned area
  - Manage leads
  - Create client accounts
  - Award bids for own projects
  - Review submissions for own projects

### 4. **Solar Vendor**
- **Custom Role:** `solar_vendor`
- **Access:** Vendor Dashboard
- **Permissions:**
  - View marketplace
  - Submit bids (coverage-restricted)
  - Submit step proofs
  - Expand coverage areas
  - View earnings

### 5. **Solar Client**
- **Custom Role:** `solar_client`
- **Access:** Client Dashboard
- **Permissions:**
  - View own projects
  - Track progress
  - Submit comments
  - Make payments

---

## 🔄 Project Workflow

### Project Lifecycle States

| Status | Description | Who Can Set |
|--------|-------------|-------------|
| `pending` | Newly created, unassigned | System (default) |
| `assigned` | Vendor assigned to project | Admin, Area Manager |
| `in_progress` | Work has started | Admin, Manager, Vendor |
| `completed` | Project finished | Admin, Manager |
| `cancelled` | Project cancelled | Admin, Area Manager |

### Workflow Diagram

```
┌─────────────┐
│   Create    │ ──→ post_status: 'publish'
│   Project   │     project_status: 'pending'
└──────┬──────┘
       │
       ├──→ Manual Assignment ──────┐
       │                            │
       └──→ Bidding Mode ───┐       │
                            │       │
                      ┌─────▼───────▼─────┐
                      │  Vendor Assigned   │
                      │    post_status:    │
                      │     'publish'      │
                      │ project_status:    │
                      │    'assigned'      │
                      └─────────┬──────────┘
                                │
                      ┌─────────▼──────────┐
                      │  Vendor Submits    │
                      │  Process Steps     │
                      └─────────┬──────────┘
                                │
                      ┌─────────▼──────────┐
                      │ Admin/Manager      │
                      │ Reviews & Approves │
                      └─────────┬──────────┘
                                │
                         ┌──────▼──────┐
                         │  Completed  │
                         │ or Rejected │
                         └─────────────┘
```

### Bidding Flow

```
1. Project Created (vendor_assignment_method: 'bidding')
   ↓
2. Published to Marketplace
   ↓
3. Vendors Submit Bids
   │ ✓ Coverage validation
   │ ✓ Bid amount & details
   │ ✓ Open/Hidden bid type
   ↓
4. Admin/Area Manager Reviews Bids
   ↓
5. Award Project to Vendor
   │ → Sets _assigned_vendor_id
   │ → Sets project_status: 'assigned'
   │ → Sends notifications
   ↓
6. Vendor Starts Work
```

---

## 📊 Meta Fields Reference

### Project Meta Fields

#### Core Project Information
| Meta Key | Type | Description | Example |
|----------|------|-------------|---------|
| `_project_state` | String | Project location state | "Maharashtra" |
| `_project_city` | String | Project location city | "Mumbai" |
| `_solar_system_size_kw` | Float | Solar system capacity | "10.5" |
| `_client_address` | Text | Installation address | "123 Main St..." |
| `_client_phone_number` | String | Client contact | "9876543210" |
| `_project_start_date` | Date | Project start date | "2024-01-15" |

#### Financial Fields
| Meta Key | Type | Description |
|----------|------|-------------|
| `_total_project_cost` | Decimal | Total price quoted to client |
| `_paid_amount` | Decimal | Amount received from client |
| `_paid_to_vendor` | Decimal | Amount paid/to pay to vendor |
| `_vendor_paid_amount` | Decimal | Actual amount paid to vendor |
| `_company_profit` | Decimal | Revenue - Vendor Cost |

#### Assignment Fields
| Meta Key | Type | Description |
|----------|------|-------------|
| `_vendor_assignment_method` | String | 'manual' or 'bidding' |
| `_assigned_vendor_id` | Integer | Assigned vendor user ID |
| `_client_user_id` | Integer | Client user ID |
| `_created_by_area_manager` | Integer | Area manager user ID |

#### Bidding Fields
| Meta Key | Type | Description |
|----------|------|-------------|
| `winning_vendor_id` | Integer | Bid winner user ID |
| `winning_bid_amount` | Decimal | Winning bid amount |

#### Workflow Status
| Meta Key | Type | Description | Values |
|----------|------|-------------|--------|
| `project_status` | String | Project lifecycle state | pending, assigned, in_progress, completed, cancelled |

**⚠️ IMPORTANT:** 
- WordPress `post_status` should **ALWAYS** remain `'publish'` for active projects
- Workflow tracking uses `project_status` meta field (WITHOUT underscore)
- Never change `post_status` to custom values!

---

### Vendor User Meta Fields

| Meta Key | Type | Description |
|----------|------|-------------|
| `company_name` | String | Vendor's company name |
| `phone` | String | Contact number |
| `purchased_states` | Array | States with coverage |
| `purchased_cities` | Array | Cities with coverage |
| `vendor_approval_status` | String | 'pending'/'approved'/'rejected' |
| `payment_completed` | Boolean | Registration payment status |
| `email_verified` | Boolean | Email verification status |
| `approved_at` | DateTime | Approval timestamp |

---

## 📁 Complete File Structure

```
Kritim Solar Core/
│
├── unified-solar-dashboard.php          # Main plugin file
│
├── includes/                             # Core functionality
│   ├── class-admin-menus.php            # Admin menu registration
│   ├── class-admin-widgets.php          # Dashboard widgets
│   ├── class-api-handlers.php           # AJAX endpoints
│   ├── class-custom-metaboxes.php       # Project metaboxes
│   ├── class-notifications-manager.php   # Notification system
│   ├── class-post-types-taxonomies.php  # CPT registration
│   ├── class-process-steps-manager.php  # Step management
│   ├── class-razorpay-client.php        # Payment integration
│   └── ajax-get-project-details.php     # Project data endpoint
│
├── admin/                                # Admin views
│   └── views/
│       ├── view-general-settings.php    # Plugin settings
│       ├── view-project-reviews.php     # Review submissions
│       ├── view-team-analysis.php       # Analytics
│       └── view-vendor-approval.php     # Vendor approvals
│
├── public/                               # Frontend views
│   └── views/
│       ├── single-solar_project.php     # Single project page
│       ├── view-area-manager-dashboard.php
│       ├── view-client-dashboard.php
│       ├── view-marketplace.php
│       ├── view-vendor-dashboard.php
│       ├── view-vendor-registration.php
│       └── view-vendor-status.php
│
├── assets/                               # Frontend assets
│   ├── css/
│   │   ├── admin-dashboard-widgets.css
│   │   ├── area-manager-dashboard.css
│   │   ├── dashboard.css
│   │   ├── marketplace.css
│   │   ├── vendor-dashboard.css
│   │   └── vendor-registration.css
│   │
│   ├── js/
│   │   ├── admin.js                     # Admin functionality
│   │   ├── area-manager-dashboard.js
│   │   ├── dashboard.js
│   │   ├── marketplace.js
│   │   ├── project-bid.js
│   │   └── vendor-registration.js
│   │
│   └── data/
│       └── indian-states-cities.json    # Location data
│
└── PLUGIN_DOCUMENTATION.md              # This file
```

---

## 🔌 API Endpoints (AJAX Actions)

### Public (Non-logged-in) Endpoints
| Action | File | Purpose |
|--------|------|---------|
| `complete_vendor_registration` | class-api-handlers.php | Complete vendor signup |
| `check_email_exists` | class-api-handlers.php | Email validation |
| `get_coverage_areas` | class-api-handlers.php | Load states/cities |
| `create_razorpay_order` | class-api-handlers.php | Payment order creation |

### Vendor Endpoints
| Action | File | Purpose |
|--------|------|---------|
| `submit_project_bid` | class-api-handlers.php | Submit bid on project |
| `submit_vendor_step` | class-api-handlers.php | Upload step proof |
| `resubmit_vendor_step` | class-api-handlers.php | Re-upload rejected step |
| `add_vendor_coverage` | class-api-handlers.php | Expand coverage area |
| `update_vendor_profile` | class-api-handlers.php | Edit profile |
| `get_vendor_earnings_chart_data` | class-api-handlers.php | Earnings data |

### Client Endpoints
| Action | File | Purpose |
|--------|------|---------|
| `client_submit_step_comment` | class-api-handlers.php | Comment on step |
| `record_client_payment` | class-api-handlers.php | Record payment |

### Area Manager Endpoints
| Action | File | Purpose |
|--------|------|---------|
| `get_area_manager_dashboard_stats` | class-api-handlers.php | Dashboard metrics |
| `get_area_manager_projects` | class-api-handlers.php | Load projects |
| `create_solar_project` | class-api-handlers.php | Create new project |
| `get_area_manager_leads` | class-api-handlers.php | Load leads |
| `create_solar_lead` | class-api-handlers.php | New lead |
| `send_lead_message` | class-api-handlers.php | Email/WhatsApp lead |
| `create_client_from_dashboard` | class-api-handlers.php | Create client account |
| `award_project_to_vendor` | class-api-handlers.php | Award bid |

### Admin/Manager Endpoints
| Action | File | Purpose |
|--------|------|---------|
| `review_vendor_submission` | class-api-handlers.php | Approve/reject step |
| `update_vendor_status` | class-api-handlers.php | Approve vendor |
| `update_vendor_details` | class-api-handlers.php | Edit vendor info |
| `filter_marketplace_projects` | class-api-handlers.php | Marketplace filter |

### REST API Endpoints
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/solar/v1/client-notifications` | GET | Fetch client notifications |
| `/solar/v1/client-comments` | GET | Fetch project comments |
| `/solar/v1/vendor-notifications` | GET | Fetch vendor notifications |
| `/solar/v1/vendor-notifications/{id}` | DELETE | Dismiss notification |

---

## 🔔 Notifications System

### Notification Types
| Type | Triggered By | Recipients |
|------|--------------|------------|
| `bid_received` | Vendor submits bid | Admin, Area Manager |
| `vendor_assigned` | Project awarded | Client |
| `step_submitted` | Vendor uploads proof | Admin, Area Manager, Client |
| `step_approved` | Admin approves step | Vendor, Client |
| `step_rejected` | Admin rejects step | Vendor |
| `vendor_approved` | Admin approves vendor | Vendor |

### Notification Channels
1. **In-App:** Stored in `wp_solar_notifications` table
2. **Email:** WordPress `wp_mail()` function
3. **WhatsApp:** URL redirection with message

### Hook System
```php
// Available Hooks
do_action('sp_bid_submitted', $bid_id, $project_id, $vendor_id, $bid_amount);
do_action('sp_vendor_step_submitted', $step_id, $project_id);
do_action('sp_step_reviewed', $step_id, $project_id, $decision);
do_action('sp_vendor_approved', $user_id);
```

---

## 💳 Payment Integration

### Razorpay Configuration
- **Test Keys:** Configurable in plugin settings
- **Live Keys:** Configurable in plugin settings
- **Currency:** INR (Indian Rupees)

### Payment Types
1. **Vendor Registration**
   - Per-state fee (configurable)
   - Per-city fee (configurable)
   - One-time payment

2. **Coverage Expansion**
   - Additional states/cities
   - Incremental pricing

### Payment Flow
```
1. User selects coverage
   ↓
2. System calculates total (states × rate + cities × rate)
   ↓
3. Create Razorpay order (server-side)
   ↓
4. Open Razorpay checkout (client-side)
   ↓
5. Payment completion callback
   ↓
6. Update user meta (purchased_states, purchased_cities)
   ↓
7. Auto-approve vendor (if email also verified)
```

---

## 🔒 Security Implementation

### Authentication
- WordPress core authentication
- Role-based access control
- `current_user_can()` checks on all endpoints

### Authorization Levels
```php
// Admin or Manager
current_user_can('manage_options') || in_array('manager', $roles)

// Area Manager
in_array('area_manager', $roles)

// Vendor
in_array('solar_vendor', $roles)

// Client
in_array('solar_client', $roles)
```

### AJAX Security
- **Nonce verification** on ALL ajax actions
- **Sanitization** of all user inputs
- **Escaping** of all outputs

### Input Sanitization Functions Used
- `sanitize_text_field()`
- `sanitize_email()`
- `sanitize_textarea_field()`
- `intval()` / `floatval()`
- `wp_kses()` for HTML

### Output Escaping Functions Used
- `esc_html()`
- `esc_attr()`
- `esc_url()`
- `esc_js()`

### SQL Security
- `$wpdb->prepare()` for all custom queries
- WordPress query builder (WP_Query) for standard queries

---

## 🎨 Frontend Features

### Marketpl Coverage Enforcement
- Vendors can only bid within purchased coverage
- Modal popup for coverage expansion
- Redirect to dashboard coverage section

### Responsive Design
- Mobile-friendly dashboards
- Touch-optimized controls
- Viewport meta tags

### Modern UI/UX
- Gradient backgrounds
- Smooth animations
- Card-based layouts
- Toast notifications
- Modal dialogs

---

## 📈 Analytics & Reporting

### Dashboard Metrics

#### Admin Dashboard
- Total projects count
- Revenue vs Costs
- Profit margins
- Lead conversion rates
- Recent activity feed
- Bid submissions

#### Area Manager Dashboard
- Projects created
- Revenue generated
- Profit earned
- Lead management
- Vendor approvals

#### Vendor Dashboard
- Total earnings
- Active projects
- Completed projects
- Available marketplace projects

#### Client Dashboard
- Project progress
- Payment summary
- Vendor information
- Timeline visualization

---

## 🚀 Configuration

### Plugin Settings (`/wp-admin/admin.php?page=sp-general-settings`)
- Razorpay API Keys (test/live)
- Per-state fee
- Per-city fee
- Default project image
- WhatsApp integration toggle
- Email notification settings

---

## 📝 Development Notes

### WordPress Compatibility
- **Minimum WordPress Version:** 5.0+
- **Tested up to:** 6.4
- **PHP Version:** 7.4+
- **Database:** MySQL 5.6+

### Dependencies
- **jQuery** (bundled with WordPress)
- **Chart.js** (CDN)
- **Razorpay Checkout** (CDN)

### Custom Post Types
- `solar_project` (Main project CPT)
- `solar_lead` (Lead management)

### Page Templates
The plugin creates default pages on activation:
- Area Manager Dashboard (`/area-manager-dashboard/`)
- Vendor Registration (`/vendor-registration/`)
- Project Marketplace (`/project-marketplace/`)
- Vendor Status (`/vendor-status/`)
- Solar Dashboard (`/solar-dashboard/`) - Unified client/vendor dashboard

---

## 🐛 Common Issues & Solutions

### Issue: Projects disappear after assignment
**Cause:** `post_status` was being changed to custom 'assigned' status  
**Solution:** Keep `post_status` as 'publish', use `project_status` meta for workflow

### Issue: Vendor can't see assigned projects
**Cause:** Meta key mismatch (`_assigned_vendor_id` vs `assigned_vendor_id`)  
**Solution:** Standardized to `_assigned_vendor_id` everywhere

### Issue: Vendor can bid outside coverage
**Cause:** No validation in `submit_project_bid()`  
**Solution:** Added coverage area validation with modal popup

---

## 📞 Support & Maintenance

### Logs
- PHP errors: `wp-content/debug.log` (if WP_DEBUG enabled)
- JavaScript errors: Browser console
- AJAX responses: Network tab in DevTools

### Database Cleanup
To reset plugin data:
```sql
-- Remove meta fields
DELETE FROM wp_postmeta WHERE meta_key LIKE '%project%' OR meta_key LIKE '%vendor%';

-- Remove custom tables
DROP TABLE wp_project_bids;
DROP TABLE wp_solar_vendor_payments;
DROP TABLE wp_solar_process_steps;
DROP TABLE wp_solar_notifications;
```

---

## 📄 License
This plugin is proprietary software developed for Kritim Solar.

---

**Last Updated:** 2024-11-29  
**Version:** 1.0.0  
**Documentation Version:** 1.0
