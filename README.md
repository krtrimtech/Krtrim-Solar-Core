# Krtrim Solar Core

<div align="center">

![Version](https://img.shields.io/badge/version-1.5.0-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0+-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-orange.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)

**A comprehensive project management and bidding platform for solar companies**

[Website](https://www.krtrim.tech) • [Documentation](https://github.com/krtrimtech/Krtrim-Solar-Core/wiki) • [Report Bug](https://github.com/krtrimtech/Krtrim-Solar-Core/issues) • [Request Feature](https://github.com/krtrimtech/Krtrim-Solar-Core/issues)

[![Sponsor](https://img.shields.io/badge/💖%20Sponsor-Support%20My%20Work-ff69b4?style=for-the-badge&logo=github&logoColor=white)](https://github.com/sponsors/shyanukant)
</div>

---

## 📋 Table of Contents

- [About](#about)
- [Features](#features)
- [Screenshots](#screenshots)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Contributing](#contributing)
- [License](#license)
- [Support](#support)
- [Credits](#credits)

---

## 🌟 About

**Krtrim Solar Core** is a free, open-source WordPress plugin designed specifically for solar energy companies to manage their projects, vendors, and clients efficiently. Built with modern web technologies and WordPress best practices, it provides a complete solution for solar business operations.

### Why Krtrim Solar Core?

- ✅ **100% Free & Open Source** - No hidden costs, no premium versions
- ✅ **Built for Solar Companies** - Industry-specific features and workflows
- ✅ **Role-Based Access** - Separate dashboards for clients, vendors, and managers
- ✅ **Mobile-First Design** - Fully responsive on all devices
- ✅ **Razorpay Integration** - Built-in payment gateway support
- ✅ **Active Development** - Regular updates and improvements

---

## 🚀 Features

### 👥 Role-Based Dashboards

- **Client Dashboard** - Track projects, view progress, contact area managers
- **Vendor Dashboard** - Manage assigned projects, submit work updates, expand coverage
- **Area Manager Dashboard** - Oversee projects, approve work, assign vendors
- **Admin Dashboard** - Complete system control and analytics

### 📊 Project Management

- Custom project post type with comprehensive metadata
- Multi-step project workflows with approval system
- Real-time progress tracking with visual timelines
- File uploads for work evidence
- Comment system for collaboration

### 💼 Vendor Management

- Vendor registration with coverage area selection (states/cities)
- Bidding system for project assignments
- Coverage expansion with Razorpay payment integration
- Vendor approval workflow
- Performance tracking

### 🧹 Solar Cleaning Service
- **Intelligent Booking** - Auto-select projects and pre-fill system size (kW)
- **GPS Address Detection** - Use browser geolocation for quick address entry
- **subscription Plans** - Monthly, 6-month, and Yearly cleaning plans
- **Automated Invoices** - Razorpay integrated receipt generation
- **Client Linkage** - Smart collision prevention for active services

### 💰 Financial Tracking

- Detailed financial summaries and analytics
- Payment tracking (client payments, vendor payments)
- Team analysis and performance metrics
- Automated calculations and reporting

### 📱 Modern UI/UX

- Mobile-responsive design
- Mobile bottom navigation
- Clean, modern interface
- Smooth animations and transitions
- Intuitive user flows

---

## 📸 Screenshots

*Coming soon - Add your screenshots here*

---

## 💾 Installation

### Automatic Installation

1. Log in to your WordPress admin panel
2. Navigate to **Plugins** → **Add New**
3. Search for "Krtrim Solar Core"
4. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the latest release from [GitHub](https://github.com/krtrimtech/Krtrim-Solar-Core/releases)
2. Upload the `Krtrim-Solar-Core` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Razorpay account (for payment features)

---

## 🎯 Usage

### Initial Setup

1. **Configure Settings**: Go to **Solar Dashboard** → **Settings**
2. **Set Up Razorpay**: Add your Razorpay API keys
3. **Create Default Steps**: Define process steps for projects
4. **Add Area Managers**: Create user accounts with `area_manager` role

### Creating Your First Project

1. Navigate to **Solar Projects** → **Add New**
2. Fill in project details (client, location, system size, cost)
3. Assign to an area manager
4. Choose vendor assignment method (manual or bidding)
5. Publish the project

### Vendor Registration

Vendors can register at `/vendor-registration/` page:
1. Fill company information
2. Select coverage areas (states/cities)
3. Complete payment via Razorpay
4. Wait for admin approval

### Solar Cleaning Service
1. Navigate to **Book Solar Cleaning** page
2. (For Clients) Select your solar project; kW will auto-fill
3. (For Guests) Enter name, email, and plant capacity manually
4. Use **📍 Use My Location** for automated address entry
5. Select a plan and complete payment
6. Track visit progress and invoices in your Client Dashboard

### Client Access

Clients view their projects at `/solar-dashboard/`:
- Check project progress
- View timeline and milestones
- Contact area managers
- Track payments

---

## ⚙️ Configuration

### Payment Gateway (Razorpay)

1. Go to **Solar Dashboard** → **General Settings**
2. Add Razorpay **Key ID** and **Key Secret**
3. Enable Test/Live mode
4. Configure coverage pricing:
   - State fee: ₹500 (default)
   - City fee: ₹100 (default)

### Process Steps

1. Navigate to **Solar Dashboard** → **Process Steps Template**
2. Define your workflow steps (e.g., Site Visit, Installation, Testing)
3. Steps are auto-created for new projects

### Custom Roles

The plugin creates these custom roles:
- `solar_client` - Project clients
- `solar_vendor` - Service providers
- `area_manager` - Regional managers
- `manager` - Senior managers

---

## 🤝 Contributing

We welcome contributions from the community! Please read our [Contributing Guidelines](CONTRIBUTING.md) before submitting pull requests.

### Quick Start for Contributors

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

---

## 📄 License

This project is licensed under the **GPL-2.0+ License** - see the [LICENSE.txt](LICENSE.txt) file for details.

---

## 💖 Support

If you find this plugin helpful, consider supporting its development:

- ⭐ **Star this repository** on GitHub
- 🐛 **Report bugs** or **request features** via [Issues](https://github.com/krtrimtech/Krtrim-Solar-Core/issues)
- 💬 **Spread the word** - share with other solar companies
- 💰 **Sponsor Development**:
  - GitHub Sponsors: [https://github.com/sponsors/shyanukant](https://github.com/sponsors/shyanukant)
  - UPI: `shyanukant@upi`

---

## 👨‍💻 Author

**Shyanukant (Krtrim Tech)**

- Website: [https://www.krtrim.tech](https://www.krtrim.tech)
- GitHub: [@shyanukant](https://github.com/shyanukant)
- Email: [contact@krtrim.tech](mailto:contact@krtrim.tech)

---

## 🙏 Acknowledgments

- WordPress community for excellent documentation
- All contributors who help improve this plugin
- Solar companies using and providing feedback

---

## 📞 Contact & Support

- **Email**: contact@krtrim.tech
- **Website**: https://www.krtrim.tech
- **Issues**: https://github.com/krtrimtech/Krtrim-Solar-Core/issues

---

<div align="center">

**Made with ❤️ by Krtrim Tech**

[⬆ Back to Top](#krtrim-solar-core)

</div>
