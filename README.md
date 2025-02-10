# API Sentinel - Secure API Key Authentication for Drupal

![Drupal 11](https://img.shields.io/badge/Drupal-11-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-brightgreen.svg)
![Packagist](https://img.shields.io/badge/Composer-Compatible-yellow.svg)

## 🚀 Overview

**API Sentinel** is a **Drupal module** that provides **API key-based authentication** for RESTful and JSON:API endpoints.
It enables site administrators to secure API access using robust authentication mechanisms, detailed logging, and customizable settings.

### 🔑 Features:

- **API Key Authentication:** Secure your REST API & JSON:API endpoints with unique API keys.
- **Key Management:** Generate, revoke, and regenerate API keys on a per-user basis.
- **Bulk Generation:** Optionally generate API keys in bulk for users by specific roles.
- **IP Whitelisting & Blacklisting:** Control API access based on trusted or blocked IP addresses.
- **Path Restriction:** Limit API access to defined paths (e.g., /api/*).
- **Custom Headers:** Configure the authentication header (default: X-API-KEY; can be changed to, for example, X-Custom-Auth).
- **Request Logging & Monitoring:** Log API key usage and monitor security events.
- **Event Integration:** Leverages Drupal hooks and Symfony events (on user login, entity creation, cache flush, etc.) for extended customization.

---

## 📦 Installation

### **1. Install via Composer**

#### Add the module repository to Composer, require the module, and then enable it:

```sh
composer config repositories.api-sentinel vcs https://github.com/30fyllos/API-Sentinel.git
composer require gt/api-sentinel:dev-main
drush en api_sentinel -y
drush cr
```

### **2. Enable the Module**
- Navigate to **Admin → Extend** (`/admin/modules`) and enable **API Sentinel**.

---

## ⚙️ Configuration

### **API Key Management**
- Go to **Admin → Configuration → API Sentinel** (`/api-sentinel/dashboard`).
- Set permissions to user to manage their own API key (`/api-sentinel/overview`).
- Generate, revoke, or regenerate API keys for users.
- Bulk generate API keys for specific user roles.

### **Restrict API Access by Paths**
- Define allowed API paths:
  ```sh
  /api/*
  /node/article/*
  ```
- Save configuration.

### **IP Whitelisting & Blacklisting**
- Manage allowed or blocked IP addresses to further restrict API access.

### **Set Custom Authentication Header**
- By default, API Sentinel uses the header: `X-API-KEY`
- This can be customized via settings (e.g., to `X-Custom-Auth`).

---

## 🔍 Making Requests with API Keys

### **1. GET Request Example**
- Include the API key in your request header:
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
  drush pm-uninstall api_sentinel -y
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
