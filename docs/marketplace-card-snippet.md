# Project Details Shortcodes

Simple shortcodes to display solar project information in Elementor Loop Grid.

**File Location:** `includes/shortcode-project-details.php`

---

## 🚀 Quick Setup

### Step 1: Include the Shortcode File

Add this line to `unified-solar-dashboard.php` in the `__construct()` function (around line 60):

```php
require_once $this->dir_path . 'includes/shortcode-project-details.php';
```

### Step 2: Use Shortcodes in Elementor

In your Elementor Loop Grid item template, add a **Shortcode Widget** and use any of the shortcodes below.

---

## 📋 Available Shortcodes

### 1. **All Details Combined** - `[project_details]`

Displays location, size, status, and description in a simple format.

**Usage:**
```
[project_details]
```

**Output:**
```
Location: Delhi, Delhi
System Size: 10 kW
Status: In Progress
Description: Solar panel installation project...
```

---

### 2. **Individual Field Shortcodes**

Use these if you want more control in Elementor layout:

| Shortcode | Output Example |
|-----------|----------------|
| `[project_location]` | Delhi, Delhi |
| `[project_size]` | 10 kW |
| `[project_status]` | In Progress |
| `[project_description]` | Project description text... |

---

## 🎨 Elementor Setup Example

### Layout 1: Using Combined Shortcode

1. Add **Shortcode Widget**
2. Enter: `[project_details]`
3. Done! ✅

### Layout 2: Using Individual Shortcodes with Elementor Widgets

```
┌─────────────────────────────┐
│  [Heading Widget - Dynamic] │  ← Post Title
├─────────────────────────────┤
│  [Text Widget]              │  
│  📍 [project_location]      │  ← Shortcode widget
├─────────────────────────────┤
│  [Text Widget]              │
│  ⚡ [project_size]          │  ← Shortcode widget
├─────────────────────────────┤
│  [Text Widget]              │
│  Status: [project_status]   │  ← Shortcode widget
├─────────────────────────────┤
│  [Text Widget]              │
│  [project_description]      │  ← Shortcode widget
└─────────────────────────────┘
```

**How to Build:**

1. **Row 1:** Add **Heading Widget** → Set Dynamic Tag → Post Title
2. **Row 2:** Add **Text Widget** → Type "📍" → Add **Shortcode Widget** → Enter `[project_location]`
3. **Row 3:** Add **Text Widget** → Type "⚡" → Add **Shortcode Widget** → Enter `[project_size]`
4. **Row 4:** Add **Shortcode Widget** → Enter `[project_status]`
5. **Row 5:** Add **Shortcode Widget** → Enter `[project_description]`

---

## 🎯 Simple Inline Usage

You can also use shortcodes inline in Text Editor widgets:

```
Location: [project_location]
Size: [project_size]
Status: [project_status]

[project_description]
```

---

## 📝 Notes

- **Description Source:** Uses post excerpt. To use full content, edit line 27 in `shortcode-project-details.php` and change `get_the_excerpt()` to `get_the_content()`
- **Status Formatting:** Automatically converts `in_progress` to `In Progress`
- **Empty Fields:** If a field is empty, it won't display
- **Loop Compatibility:** All shortcodes work automatically in Elementor loops

---

## 🔧 Customization

To customize the output, edit `/includes/shortcode-project-details.php`:

- **Line 27:** Change description source (excerpt vs full content)
- **Lines 35-58:** Modify HTML structure and labels
- **Lines 68-91:** Customize individual shortcode outputs

