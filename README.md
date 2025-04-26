# ZJmfOAuth 🔐 - 全能 OAuth 登录插件 for IDCsmart

[![MIT License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg)](https://php.net/)
[![IDCsmart 2.5+](https://img.shields.io/badge/IDCsmart-2.5%2B-blue.svg)](https://idcsmart.com)
![Platforms](https://img.shields.io/badge/Supported_Platforms-10+-success.svg)

**智简魔方 (IDCsmart) 全能 OAuth 登录插件，一键集成 10+ 主流身份认证平台，为您的 IDCsmart 系统提供现代化、安全、便捷的登录解决方案。**

![登录界面预览](https://via.placeholder.com/1280x600.png?text=OAuth+Login+Interface+Preview)
*(请将上方链接替换为实际的插件登录界面截图)*

---

## 🌟 核心功能

* 🚀 **广泛兼容**: 快速接入 **10+ 主流平台** (持续增加中)。
* 🔒 **安全可靠**: 采用 **AES-256-CBC** 对敏感信息进行军工级加密，配合 **TLS 1.3** 保障传输安全。
* 🧠 **智能路由**: 自动选择最优 API 端点，提升响应速度和稳定性。
* 📊 **数据看板**: 提供实时的登录统计图表，方便监控用户活动。
* 🌐 **多语言支持**: 内建 **中文/英文** 双语界面，轻松切换。
* 📱 **跨设备兼容**: 完美适配 PC 和移动端设备，提供一致的用户体验。
* 📜 **审计日志**: 详细记录所有 OAuth 登录事件，便于追踪和审计。

---

## 📚 目录

1.  [支持平台](#-支持平台)
2.  [安装指南](#-安装指南)
3.  [全平台配置教程](#️-全平台配置教程)
    * [Apple](#apple-配置)
    * [GitHub](#github-配置)
    * [Google](#google-配置)
    * [GitLab](#gitlab-配置)
    * [Authing](#authing-配置)
    * [MetaMask](#metamask-配置)
    * [Slack](#slack-配置)
    * [Atlassian](#atlassian-配置)
4.  [使用示例](#-使用示例)
5.  [常见问题排查](#-常见问题排查)
6.  [贡献指南](#-贡献指南)
7.  [安全声明](#-安全声明)
8.  [许可证](#-许可证)
9.  [支持与联系](#-支持与联系)

---

## 🌐 支持平台

| 平台                                                       | 状态      | 配置文档               |
| :--------------------------------------------------------- | :-------- | :--------------------- |
| ![Apple](https://img.shields.io/badge/Apple-000000?logo=apple) | ✅ 稳定   | [配置](#apple-配置)    |
| ![GitHub](https://img.shields.io/badge/GitHub-181717?logo=github) | ✅ 稳定   | [配置](#github-配置)   |
| ![Google](https://img.shields.io/badge/Google-4285F4?logo=google) | ✅ 稳定   | [配置](#google-配置)   |
| ![GitLab](https://img.shields.io/badge/GitLab-FCA121?logo=gitlab) | ✅ 稳定   | [配置](#gitlab-配置)   |
| ![Authing](https://img.shields.io/badge/Authing-1E6CFF?logo=auth0) | ✅ 稳定   | [配置](#authing-配置)  |
| ![MetaMask](https://img.shields.io/badge/MetaMask-F6851B?logo=metamask) | 🚧 开发中 | [配置](#metamask-配置) |
| ![Slack](https://img.shields.io/badge/Slack-4A154B?logo=slack) | ✅ 稳定   | [配置](#slack-配置)    |
| ![Atlassian](https://img.shields.io/badge/Atlassian-0052CC?logo=atlassian) | ✅ 稳定   | [配置](#atlassian-配置)|

*(✅ 稳定 | 🚧 开发中 | ❌ 暂不支持)*

---

## 📦 安装指南

### 环境要求

* **PHP**: ≥ 7.4 (推荐 7.4.28 或更高版本)
* **IDCsmart**: ≥ 2.5.1
* **PHP 扩展**:
    * `OpenSSL` ≥ 1.1.1+
    * `cURL` ≥ 7.64+
    * `JSON`
    * `Mbstring`

### 安装步骤

#### 方式一：Composer 安装 (推荐)

```bash
# 进入您的 IDCsmart 根目录
cd /path/to/your/idcsmart

# 安装插件
composer require zjmf/oauth-plugin @stable

# 清除 IDCsmart 缓存 (根据您的实际情况可能需要)
# php artisan cache:clear
# php artisan config:clear
# php artisan view:clear
