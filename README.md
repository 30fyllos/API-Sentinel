# API Sentinel - Secure API Key Authentication for Drupal

![Drupal 11](https://img.shields.io/badge/Drupal-11-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-brightgreen.svg)
![Packagist](https://img.shields.io/badge/Composer-Compatible-yellow.svg)

## 🚀 Overview

**API Sentinel** is a **Drupal module** that provides **API key-based authentication** for RESTful and JSON:API endpoints.
It allows site administrators to **secure API access** with custom authentication rules.

### 🔑 Features:

- API Key Authentication for REST API & JSON:API
- Key Generation, Revocation & Regeneration
- Whitelist & Blacklist IP Addresses
- Restrict API access to specific paths (`/api/*`)
- Custom Authentication Header Support
- Bulk API Key Generation by Role
- Request Logging & Security Monitoring

---

## 📦 Installation

### **1. Install via Composer**
- Add the module repository to Composer.
- Require the module via Composer.
- Enable the module in Drupal.

```sh
composer config repositories.api-sentinel vcs https://github.com/30fyllos/API-Sentinel.git
composer require 30fyllos/api-sentinel:dev-main
drush en api_sentinel -y
drush cr
```

### **2. Enable the Module**
- Navigate to **Admin → Extend** (`/admin/modules`) and enable **API Sentinel**.

---

## ⚙️ Configuration

### **API Key Management**
- Go to **Admin → Configuration → API Sentinel** (`/admin/config/api-sentinel`).
- Generate, revoke, or regenerate API keys for users.
- Bulk generate API keys for specific user roles.

### **Restrict API Access by Paths**
- Navigate to **Admin → Configuration → API Sentinel Settings** (`/admin/config/api-sentinel/settings`).
- Define allowed API paths:
  ```sh
  /api/*
  /jsonapi/node/article/*
  ```
- Save configuration.

### **Whitelist & Blacklist IPs**
- Block or allow specific IP addresses.

### **Set Custom Authentication Header**
- Default: `X-API-KEY`
- Can be changed to `X-Custom-Auth` or another custom header.

---

## 🔍 Using API Keys in Requests

### **1. GET Request with API Key**
- Send a request using the API key in the request header.
  ```sh
  curl -X GET "http://your-drupal-site/api/secure-data" \
     -H "X-API-KEY: YOUR_GENERATED_API_KEY"
  ```

### **2. Custom Header Authentication**
- If a custom header is set (e.g., `X-Custom-Auth`), use it instead of `X-API-KEY`.
  ```sh
  curl -X GET "http://your-drupal-site/api/secure-data" \
     -H "X-Custom-Auth: YOUR_API_KEY"
  ```

---

## 🛠️ Uninstallation

- Disable and remove API Sentinel from Drupal.
- Clear cache to remove stored configurations.
  ```sh
  ```

---

## 📜 License

This project is licensed under the **GPL-2.0-or-later** license.

---

## 🤝 Contributing

Pull requests are welcome! Open an issue for feature requests or bug reports.

---

## 📧 Support

For support, open an issue in the **[GitHub Repository](https://github.com/30fyllos/API-Sentinel/issues)**.
