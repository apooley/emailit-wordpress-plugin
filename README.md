# Emailit Email API Integration Repository

This repository contains the **Emailit Integration for WordPress** plugin, a comprehensive solution that replaces WordPress's default `wp_mail()` function with Emailit's email service API.

![WordPress](https://img.shields.io/badge/WordPress-5.7+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-green.svg)
![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)
![Version](https://img.shields.io/badge/Version-3.0.4-green.svg)

## 📁 Repository Contents

### 🎯 Main Plugin: [emailit-integration/](./emailit-integration/)
The complete WordPress plugin with enterprise-grade email functionality and improved UX design:
- **Complete wp_mail() replacement** with Emailit API integration
- **FluentCRM integration** for advanced bounce handling and CRM synchronization
- **Real-time webhook tracking** with optional webhook listening
- **Queue system** for bulk email processing
- **Advanced error handling** with circuit breaker and intelligent retry mechanisms
- **🎨 Improved Admin Interface** with Power User Mode and progressive disclosure
- **Enterprise security features** including encrypted API key storage

**👉 [View Complete Plugin Documentation](./emailit-integration/README.md)**

## 🚀 Quick Overview

The Emailit Integration plugin transforms WordPress email delivery with:

### ✨ **Key Features**
- **🔄 Complete wp_mail() Replacement**: Seamless integration with all WordPress functionality
- **🤝 FluentCRM Integration**: Automatic bounce handling and CRM synchronization
- **📊 Real-time Tracking**: Webhook-based email status updates (delivered, bounced, etc.)
- **⚡ Queue System**: Background processing for bulk emails
- **🛡️ Advanced Error Handling**: Circuit breaker, intelligent retry, and error analytics
- **🎨 Improved Admin Interface**: Power User Mode with customizable complexity
- **🔒 Enterprise Security**: Encrypted API keys and comprehensive input validation
- **🛡️ Fallback System**: Automatic failover to wp_mail() if API fails

### 📋 **Requirements**
- **WordPress:** 5.7 or higher
- **PHP:** 8.0 or higher
- **Emailit Account:** Active Emailit account with API key
- **Server:** Support for outgoing HTTP requests (`wp_remote_post`)
- **SSL Certificate:** Recommended for webhook endpoints

## 🚀 Getting Started

### 📥 **Quick Installation**

1. **Clone Repository**
   ```bash
   git clone https://github.com/apooley/Emailit-Email-API.git
   cd Emailit-Email-API
   ```

2. **Install Plugin**
   - Copy `emailit-integration/` to `/wp-content/plugins/`
   - Activate through WordPress admin
   - Configure your Emailit API key

### 📖 **Complete Documentation**

For detailed installation instructions, configuration options, troubleshooting, and advanced features:

**👉 [View Complete Plugin Documentation](./emailit-integration/README.md)**

---

## 🔗 **Links**

- **Plugin Documentation**: [emailit-integration/README.md](./emailit-integration/README.md)
- **Emailit Service**: [https://emailit.com](https://emailit.com)
- **WordPress Repository**: Coming soon
- **Support**: GitHub Issues

## 📄 **License**

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

---

## 🆕 **Version 3.0.0 - Major UX Overhaul**

### 🎨 **User Interface Improvements**

#### **Power User Mode System**
- **🎛️ Toggle Interface Complexity**: Users can choose between simple and advanced interfaces
- **👤 User-Specific Preferences**: Individual power user mode settings per user
- **⚡ Real-Time Switching**: Instant interface changes without page reload
- **🎯 Adaptive Experience**: Interface adapts to user skill level and preferences

#### **Progressive Disclosure Design**
- **📁 Collapsible Advanced Sections**: Advanced features organized in expandable sections
- **🔽 Smart Defaults**: Sections start collapsed to reduce visual clutter
- **🎭 Smooth Animations**: 300ms slide animations for better user experience
- **👁️ Visual Indicators**: Clear expand/collapse icons and hover effects

#### **Simplified Tab Structure**
- **📊 Consolidated Navigation**: Reduced from 6 tabs to 3 clear sections
  - **General**: Essential settings, API configuration, testing
  - **Logs & Statistics**: Email logs, webhook activity, quick stats
  - **Advanced**: Power user features (collapsible sections)
- **🎯 Logical Flow**: Intuitive progression from basic to advanced features
- **📱 Responsive Design**: Optimized for all screen sizes
### 🔧 **Technical Improvements**

#### **Advanced JavaScript Functionality**
- **⚡ AJAX-Powered Interface**: Real-time updates and interactions
- **🎛️ Dynamic UI Controls**: Toggle switches, collapsible sections, tooltips
- **🔄 State Management**: Persistent user preferences and interface state
- **🛡️ Error Handling**: Graceful fallbacks and user feedback

#### **Enhanced User Experience**
- **🎨 Modern Visual Design**: Professional styling with consistent theming
- **📱 Mobile-Responsive**: Optimized for all device sizes
- **♿ Accessibility**: Proper labels, keyboard navigation, screen reader support
- **⚡ Performance**: Optimized loading and smooth interactions

#### **Feature Organization**
- **👥 User Type Optimization**: Different experiences for beginners vs. power users
- **📚 Learning Progression**: Users can gradually explore advanced features
- **🎯 Reduced Cognitive Load**: Simplified interface for basic users
- **⚙️ Full Control**: Complete access to all features for power users

---

## 📋 **Previous Updates**

### Version 3.0.2 - Squished some bugs
- **Squished some bugs**: Fixed various issues and improved overall stability

### Version 3.0.1 - Bug Fixes & UI Improvements
- **Fixed Header Congestion**: Resolved duplicate webhook alert messages in admin settings header
- **Improved Layout**: Enhanced message box widths and spacing for better readability
- **Squashed Various Bugs**: Fixed issues related to admin-ajax calls and health views
- **Enhanced Responsiveness**: Better mobile layout and responsive design improvements
- **Webhook Alert Management**: Added proper dismissal functionality and duplicate prevention

### Version 2.6.4 - Webhook Logs & UI Improvements
- **Webhook Logs Integration**: Moved webhook logs to Webhooks tab for better UX
- **Fixed Webhook Display**: Resolved empty fields and improved data extraction
- **Test Webhook Improvements**: Clear test identification and visual indicators
- **UI/UX Enhancements**: Removed redundant stats and improved visual hierarchy

### Version 2.6.0 - Advanced Error Handling System
- **Circuit Breaker Protection**: Automatic failure detection and recovery
- **Intelligent Retry System**: Exponential backoff with jitter
- **Error Analytics**: Pattern detection and trend analysis
- **Multi-Channel Notifications**: Email, admin notices, webhooks, and Slack alerts

---

**Need detailed information?** Check the [complete plugin documentation](./emailit-integration/README.md) for comprehensive installation, configuration, and usage instructions.
