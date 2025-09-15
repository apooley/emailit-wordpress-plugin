# Emailit Email API Integration Repository

This repository contains the **Emailit Integration for WordPress** plugin, a comprehensive solution that replaces WordPress's default `wp_mail()` function with Emailit's email service API.

![WordPress](https://img.shields.io/badge/WordPress-5.7+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-green.svg)
![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)
![Version](https://img.shields.io/badge/Version-2.6.2-green.svg)

## 📁 Repository Contents

### 🎯 Main Plugin: [emailit-integration/](./emailit-integration/)
The complete WordPress plugin with enterprise-grade email functionality:
- **Complete wp_mail() replacement** with Emailit API integration
- **FluentCRM integration** for advanced bounce handling and CRM synchronization
- **Real-time webhook tracking** with optional webhook listening
- **Queue system** for bulk email processing
- **Advanced error handling** with circuit breaker and intelligent retry mechanisms
- **Professional admin interface** with modern UI design
- **Enterprise security features** including encrypted API key storage

**👉 [View Complete Plugin Documentation](./emailit-integration/README.md)**

### 📚 Additional Components
- **breakdance/**: Breakdance page builder plugin for development testing
- **fluent-crm/**: FluentCRM plugin for integration testing and development

## 🚀 Quick Overview

The Emailit Integration plugin transforms WordPress email delivery with:

### ✨ **Key Features**
- **🔄 Complete wp_mail() Replacement**: Seamless integration with all WordPress functionality
- **🤝 FluentCRM Integration**: Automatic bounce handling and CRM synchronization
- **📊 Real-time Tracking**: Webhook-based email status updates (delivered, bounced, etc.)
- **⚡ Queue System**: Background processing for bulk emails
- **🛡️ Advanced Error Handling**: Circuit breaker, intelligent retry, and error analytics
- **🎨 Modern Admin Interface**: Professional dashboard with enhanced UX
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

## 🆕 Latest Features (Version 2.6.2)

### 🐛 **Health Monitor Bug Fix**
- **Fixed Queue Table Column Error**: Resolved "Unknown column 'updated_at'" error in health monitor queue processing check
- **Enhanced Queue Health Check**: Added proper table existence validation and error handling for queue health monitoring
- **Improved Error Handling**: Added try-catch blocks and graceful fallbacks for database query failures in health checks

## 🆕 Previous Features (Version 2.6.1)

### 🐛 **Critical Bug Fixes & Stability Improvements**
- **Fixed Health Monitor Critical Errors**: Resolved E_ERROR in health monitor page
- **Fixed Database Migration Errors**: Resolved duplicate key and missing table errors
- **Fixed Metrics Collection Errors**: Removed references to non-existent database columns
- **Fixed Health Check Email Spam**: Prevented real email sending during health checks
- **Fixed Webhook Health Check Loops**: Prevented HTTP requests to self
- **Enhanced Error Handling**: Added comprehensive try-catch blocks and table existence checks
- **Improved System Stability**: Better initialization and graceful degradation

### 🛡️ **Advanced Error Handling System** (Version 2.6.0)
- **Circuit Breaker Protection**: Automatic failure detection and recovery mechanisms
- **Intelligent Retry System**: Exponential backoff with jitter for failed operations
- **Error Analytics**: Pattern detection, trend analysis, and anomaly detection
- **Multi-Channel Notifications**: Email, admin notices, webhooks, and Slack alerts
- **Error Recovery**: Automated recovery strategies for different error types
- **Debugging Tools**: Enhanced troubleshooting and error insights

### 📊 **Production-Ready Reliability**
- **Cascading Failure Prevention**: Prevents system-wide failures during outages
- **Self-Healing Mechanisms**: Automatic recovery from transient errors
- **Comprehensive Monitoring**: Real-time error statistics and health metrics
- **Data Retention**: Configurable error data cleanup and archiving
- **Performance Optimization**: Efficient error tracking and storage

---

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

---

**Need detailed information?** Check the [complete plugin documentation](./emailit-integration/README.md) for comprehensive installation, configuration, and usage instructions.
