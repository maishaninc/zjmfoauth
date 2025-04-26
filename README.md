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

*   🚀 **广泛兼容**: 快速接入 **10+ 主流平台** (持续增加中)。
*   🧠 **智能路由**: 后台配置简单

---

## 📚 目录

1.  [支持平台](#-支持平台)
2.  [安装指南](#-安装指南)
3.  [全平台配置教程](#️-全平台配置教程)
    *   [Apple](#apple-配置)
    *   [GitHub](#github-配置)
    *   [Google](#google-配置)
    *   [GitLab](#gitlab-配置)
    *   [Authing](#authing-配置)
    *   [MetaMask](#metamask-配置)
    *   [Slack](#slack-配置)
    *   [Atlassian](#atlassian-配置)
4.  [使用示例](#-使用示例)
5.  [常见问题排查](#-常见问题排查)
6.  [贡献指南](#-贡献指南)
7.  [安全声明](#-安全声明)
8.  [许可证](#-许可证)
9.  [支持与联系](#-支持与联系)

---

## 🌐 支持平台

| 平台                                                       | 状态      | 申请API               |
| :--------------------------------------------------------- | :-------- | :--------------------- |
| ![Apple](https://img.shields.io/badge/Apple-000000?logo=apple)  | ✅ 稳定   | [配置](#apple-配置)    |
| ![GitHub](https://img.shields.io/badge/GitHub-181717?logo=github)  | ✅ 稳定   | [配置](#github-配置)   |
| ![Google](https://img.shields.io/badge/Google-4285F4?logo=google)  | ✅ 稳定   | [配置](#google-配置)   |
| ![GitLab](https://img.shields.io/badge/GitLab-FCA121?logo=gitlab)  | ✅ 稳定   | [配置](#gitlab-配置)   |
| ![Authing](https://img.shields.io/badge/Authing-1E6CFF?logo=auth0)  | ✅ 稳定   | [配置](#authing-配置)  |
| ![MetaMask](https://img.shields.io/badge/MetaMask-F6851B?logo=metamask)  | 🚧 开发中 | [配置](#metamask-配置) |
| ![Slack](https://img.shields.io/badge/Slack-4A154B?logo=slack)  | ✅ 稳定   | [配置](#slack-配置)    |
| ![Atlassian](https://img.shields.io/badge/Atlassian-0052CC?logo=atlassian)  | ✅ 稳定   | [配置](#atlassian-配置)|

*(✅ 稳定 | 🚧 开发中 | ❌ 暂不支持)*

---

## 📦 安装指南

### 环境要求

*   **PHP**: ≥ 7.4 (推荐 7.4.28 或更高版本)
*   **魔方财务**: ≥ 3.7.4 (请根据实际情况修改此项)

### 安装步骤

1.  下载本项目 `https://github.com/maishaninc/zjmfoauth/archive/refs/heads/main.zip` (请替换为实际插件的下载链接或说明)
2.  将解压后的插件文件夹（例如 `metamaskoauth`）上传到您的 IDCsmart 安装目录下的 `modules/oauth/` 目录。
    ```
    网站根目录/modules/oauth/插件名称
    ```
3.  登录您的 IDCsmart 后台。
4.  导航至 `设置` -> `模块管理` -> `接口/插件` -> `登录接口`。
5.  找到新上传的 OAuth 插件并点击 `安装`。
6.  安装完成后，点击 `配置` 按钮，根据 [全平台配置教程](#️-全平台配置教程) 填写必要的 API Key、Secret 等信息。
7.  启用插件。

---

## ⚙️ 全平台配置教程

**注意:** 以下回调 URL 中的 `https://yourdomain.com` 需要替换为您 IDCsmart 系统的实际访问域名。确保您的站点已启用 HTTPS。

### Apple 配置
*(待补充，请参考 Apple Developer 文档创建 App ID 和 Service ID)*

### GitHub 配置

1.  **创建OAuth应用**:
    *   进入 [GitHub Developer Settings](https://github.com/settings/developers) -> OAuth Apps -> New OAuth App
    *   **Application name**: (例如: My IDCsmart Login)
    *   **Homepage URL**: `https://yourdomain.com`
    *   **Authorization callback URL**: `https://yourdomain.com/index.php?m=oauth&c=callback&plugin=githuboauth` (请确认此回调路径是否与您的 IDCsmart 路由匹配)
2.  **获取 Client ID 和 Client Secret**。
3.  **后台配置**: 在 IDCsmart 后台对应的插件配置中填入 Client ID 和 Client Secret。

### Google 配置

1.  **创建凭证**:
    *   进入 [Google Cloud Console](https://console.cloud.google.com/) -> APIs & Services -> Credentials -> Create Credentials -> OAuth client ID
    *   **Application type**: Web application
    *   **Authorized JavaScript origins**: `https://yourdomain.com`
    *   **Authorized redirect URIs**: `https://yourdomain.com/index.php?m=oauth&c=callback&plugin=googleoauth` (请确认此回调路径)
2.  **启用 Google People API**: 在 Google Cloud Console 中确保 People API 已启用。
3.  **获取 Client ID 和 Client Secret**。
4.  **后台配置**: 在 IDCsmart 后台对应的插件配置中填入 Client ID 和 Client Secret。

### GitLab 配置

1.  **创建应用**:
    *   登录您的 GitLab 实例 -> User Settings -> Applications -> Add new application
    *   **Name**: (例如: IDCsmart Login)
    *   **Redirect URI**: `https://yourdomain.com/index.php?m=oauth&c=callback&plugin=gitlaboauth` (请确认此回调路径)
    *   **Scopes**: 勾选 `read_user`, `openid`, `email` 权限。
2.  **获取 Application ID 和 Secret**。
3.  **后台配置**: 在 IDCsmart 后台对应的插件配置中填入 Application ID 和 Secret。

### Authing 配置

1.  **创建应用**:
    *   进入 [Authing 控制台](https://console.authing.cn/) -> 应用 -> 创建自建应用
    *   **应用类型**: 选择 Web 应用
    *   **配置登录回调 URL**: `https://yourdomain.com/index.php?m=oauth&c=callback&plugin=authingoauth` (请确认此回调路径)
2.  **记录 App ID、App Secret 和 Issuer URL** (可在应用详情页找到)。
3.  **后台配置**: 在 IDCsmart 后台对应的插件配置中填入 App ID, App Secret, 和 Issuer URL。

### MetaMask 配置 (🚧 开发中)

MetaMask 登录不依赖传统的 OAuth Client ID/Secret，而是基于数字签名验证。

1.  **前端交互**:
    *   用户点击 "使用 MetaMask 登录" 按钮。
    *   前端 JavaScript 向后端请求一个唯一的、一次性的 `nonce` (随机数)。
    *   前端构造签名消息，通常格式为 `登录消息前缀 + nonce` (例如: "Login nonce: abc123xyz")。
    *   前端调用 MetaMask (`ethereum.request({ method: 'personal_sign', ... })`) 请求用户对该消息进行签名。
    *   前端将用户的钱包地址 (`address`)、签名 (`signature`) 和使用的 `nonce` 发送到后端回调 URL。
2.  **后端验证**:
    *   后端回调接口 (`/index.php?m=oauth&c=callback&plugin=metamaskoauth`) 接收 `address`, `signature`, `nonce`。
    *   后端从 Session 或其他安全存储中获取之前为该用户生成的 `nonce`。
    *   后端验证收到的 `nonce` 与存储的 `nonce` 是否匹配。
    *   后端使用密码学库验证 `signature` 是否是由 `address` 对 `登录消息前缀 + nonce` 进行的有效签名。
    *   验证通过后，查找或创建与该 `address` 关联的用户账户，并完成登录。
3.  **后台配置**:
    *   通常只需要配置 "签名消息前缀" (Sign Message Prefix)，例如 "请签名以登录您的账户，随机码:"。

### Slack 配置

1.  **创建应用**:
    *   进入 [Slack API](https://api.slack.com/apps) -> Create New App -> From scratch
    *   **App Name**: (例如: IDCsmart Login)
    *   **Development Slack Workspace**: 选择您的工作区
    *   导航到 OAuth & Permissions -> **Redirect URLs**: 添加 `https://yourdomain.com/index.php?m=oauth&c=callback&plugin=slackoauth` (请确认此回调路径)
    *   **Scopes** -> User Token Scopes: 添加 `identity.basic`, `identity.email`。
2.  **获取 Client ID 和 Client Secret** (在 Basic Information 页面)。
3.  **后台配置**: 在 IDCsmart 后台对应的插件配置中填入 Client ID 和 Client Secret。

### Atlassian 配置

1.  **创建OAuth应用**:
    *   进入 [Atlassian Developer Console](https://developer.atlassian.com/console/myapps/) -> Create -> OAuth 2.0 integration
    *   **Name**: (例如: IDCsmart Login)
    *   **Callback URL**: `https://yourdomain.com/index.php?m=oauth&c=callback&plugin=atlassianoauth` (请确认此回调路径)
    *   **Permissions**: 添加 `read:jira-user`, `offline_access`, `read:me` (根据需要调整)。
2.  **获取 Client ID 和 Secret** (在 Settings 页面)。
3.  **后台配置**: 在 IDCsmart 后台对应的插件配置中填入 Client ID 和 Secret。

### 💡 验证清单

*   [ ] 所有回调 URL 中的 `yourdomain.com` 已替换为您的实际域名。
*   [ ] 您的 IDCsmart 站点已部署并强制启用 HTTPS。
*   [ ] 敏感信息（Client Secrets 等）已安全配置在 IDCsmart 后台，避免硬编码。
*   [ ] 各平台申请 API 时所选的权限 (Scopes) 与插件要求一致。
*   [ ] 确认 IDCsmart 系统中的回调 URL 路径 (`/index.php?m=oauth&c=callback&plugin=插件名`) 是否正确。

---

## 🚀 使用示例

*(待补充：可以添加截图或简要说明用户如何在登录页面看到并使用这些 OAuth 选项)*

---

## ❓ 常见问题排查

*   **回调 URL 错误**: 确保在第三方平台配置的回调 URL 与 IDCsmart 插件设置中的完全一致，并且域名和路径正确。
*   **API 权限不足**: 检查在第三方平台申请 API 时是否勾选了所有必要的权限 (Scopes)。
*   **Client ID/Secret 错误**: 仔细核对后台配置中填写的 ID 和 Secret 是否正确，无多余空格。
*   **HTTPS 问题**: 大部分 OAuth 提供商强制要求回调 URL 使用 HTTPS。
*   **服务器时间不同步**: 服务器时间若与标准时间相差过大，可能导致某些基于时间的验证失败。
*   **防火墙/网络问题**: 确保您的服务器可以访问第三方平台的 API 端点。
*   **MetaMask 签名验证失败**:
    *   检查签名消息是否与前端发送的一致（包括前缀和 Nonce）。
    *   确保后端使用的签名验证库正确且配置无误。
    *   确认 Nonce 是否正确传递和匹配。

*(待补充更多具体问题和解决方案)*

---

## 🤝 贡献指南

欢迎开发者为 ZJmfOAuth 贡献代码、报告 Bug 或提出新功能建议！

1.  Fork 本仓库。
2.  创建新的 Feature 分支 (`git checkout -b feature/AmazingFeature`)。
3.  提交您的更改 (`git commit -m 'Add some AmazingFeature'`)。
4.  推送 Feature 分支 (`git push origin feature/AmazingFeature`)。
5.  创建 Pull Request。

---

## 🛡️ 安全声明

*   我们强烈建议您将 Client Secrets 等敏感信息存储在安全的环境变量或 IDCsmart 的加密配置中，而不是直接硬编码在代码里。
*   定期审查并更新您在各 OAuth 平台上的应用设置和权限。
*   保持插件和 IDCsmart 系统为最新版本，以获取安全更新。

---

## 📄 许可证

本项目采用 [MIT License](LICENSE) 授权。

---

## 📞 支持与联系

*   **Bug 报告 / 功能请求**: 请通过 GitHub Issues 提交。
*   **联系作者**: [Maishan Inc](https://github.com/maishaninc) (请替换为您的联系方式或 GitHub 链接)
