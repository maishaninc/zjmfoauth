ZJmfOAuth 🔐智简魔方全能 OAuth 登录插件，旨在提供一个现代化、安全且易于集成的身份认证解决方案。通过 ZJmfOAuth，您可以轻松地将 10 多个主流的身份认证平台集成到您的 IDCsmart 系统中。🌟 核心功能广泛平台支持: 快速接入 10+ 主流身份认证平台。顶级安全保障: 采用 AES-256-CBC 加密标准，配合 TLS 1.3 传输层安全协议。智能路由优化: 自动检测并选择最优的 API 端点，提升响应速度和稳定性。实时数据看板: 提供直观的登录统计图表，方便监控用户活动。多语言界面: 内置中文和英文两种语言，满足不同用户需求。全端兼容: 完美适配 PC 桌面端和移动设备，提供一致的用户体验。详细审计日志: 完整记录所有登录尝试和事件，便于安全审计和追踪。📚 目录支持平台安装指南全平台配置教程Apple 配置GitHub 配置Google 配置GitLab 配置Authing 配置MetaMask 配置Slack 配置Atlassian 配置使用示例 (待补充具体代码或截图)常见问题排查贡献指南安全声明许可证🌐 支持平台平台图标平台名称状态配置文档Apple✅ 稳定配置GitHub✅ 稳定配置Google✅ 稳定配置GitLab✅ 稳定配置Authing✅ 稳定配置MetaMask🚧 开发中配置Slack✅ 稳定配置Atlassian✅ 稳定配置📦 安装指南环境要求PHP: ≥ 7.4 (推荐使用 7.4.28 或更高版本)IDCsmart: ≥ 2.5.1OpenSSL: ≥ 1.1.1+cURL: ≥ 7.64+安装步骤1. Composer 安装 (推荐)# 进入您的 IDCsmart 安装目录
cd /path/to/idcsmart

# 使用 Composer 安装插件
composer require zjmf/oauth-plugin @stable
2. 手动安装# 进入 IDCsmart 插件目录
cd /path/to/idcsmart/plugins

# 克隆仓库
git clone https://github.com/zjmf-project/zjmfoauth.git

# 设置权限
chmod -R 755 zjmfoauth
3. 后台激活登录您的 IDCsmart 后台管理界面。导航至 系统设置 → 插件管理。点击页面右上角的 扫描新插件 按钮。在插件列表中找到 ZJmfOAuth。点击 安装 按钮。安装完成后，启用该插件。进入插件配置界面进行后续设置。⚙️ 全平台配置教程请根据您需要集成的平台，参考以下配置指南：Apple 配置访问 Apple 开发者后台。创建新的 Identifier，选择 Services ID。配置服务:Description: (例如: YourAppName Login)Identifier (Bundle ID): com.yourcompany.yourapp (必须是唯一的反向域名格式)在服务配置中，勾选并启用 Sign In with Apple。配置回调 URL (Web Authentication Configuration):Domains and Subdomains: yourdomain.comReturn URLs: https://yourdomain.com/oauth/apple/callback记录您的 Team ID (位于右上角账户名下) 和刚创建的 Service ID (Identifier)。创建并下载 Private Key (.p8 文件)，记录 Key ID。在 IDCsmart 的 ZJmfOAuth 插件配置中填入以下信息 (或配置环境变量):APPLE_TEAM_ID=YOUR_TEAM_ID
APPLE_KEY_ID=YOUR_KEY_ID
APPLE_SERVICE_ID=com.yourcompany.yourapp # 注意：这里是 Service ID，不是 Bundle ID
APPLE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYOUR_P8_FILE_CONTENT\n-----END PRIVATE KEY-----" # 将 .p8 文件内容复制到这里，注意换行符 \n
GitHub 配置访问 GitHub Developer Settings。点击 New OAuth App。填写应用信息:Application name: (例如: YourAppName)Homepage URL: https://yourdomain.comAuthorization callback URL: https://yourdomain.com/oauth/github/callback创建应用后，记录 Client ID 和 Client Secret。在 IDCsmart 的 ZJmfOAuth 插件配置中填入 (或配置 PHP/环境变量):// 插件配置示例 (具体配置方式可能在插件后台界面)
$config->set('github', [
    'client_id'     => env('GITHUB_CLIENT_ID', 'YOUR_GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET', 'YOUR_GITHUB_CLIENT_SECRET'),
    'redirect'      => env('GITHUB_REDIRECT_URI', 'https://yourdomain.com/oauth/github/callback'),
    'scopes'        => ['user:email'], // 请求用户邮箱权限
]);
Google 配置访问 Google Cloud Console。创建新项目或选择现有项目。导航至 API 和服务 → 凭据。配置 OAuth 同意屏幕:应用类型: 选择 外部。填写应用名称、用户支持电子邮件等信息。已获授权的网域: 添加 yourdomain.com。创建 OAuth 客户端 ID:应用类型: 选择 Web 应用。已获授权的 JavaScript 来源: 添加 https://yourdomain.com。已获授权的重定向 URI: 添加 https://yourdomain.com/oauth/google/callback。创建后，记录 客户端 ID 和 客户端密钥。在 IDCsmart 的 ZJmfOAuth 插件配置中填入相应信息。GitLab 配置登录您的 GitLab 实例。进入 用户设置 (User Settings) → Applications (或管理员设置 Admin Area → Applications)。创建新应用:Name: (例如: YourAppName)Redirect URI: https://yourdomain.com/oauth/gitlab/callback (每行一个)Scopes: 勾选 read_user, openid, email。保存应用，记录 Application ID 和 Secret。在 IDCsmart 的 ZJmfOAuth 插件配置中填入相应信息。Authing 配置登录 Authing 控制台。在左侧菜单选择 应用 → 自建应用，点击 创建自建应用。选择应用类型：标准 Web 应用。填写应用名称和认证地址 (例如: yourapp)。配置回调地址: 在 应用配置 → 登录回调 URL 中添加 https://yourdomain.com/oauth/authing/callback。在 应用配置 → 端点信息 中获取并记录:App IDApp SecretIssuer URL (颁发者 URL)在 IDCsmart 的 ZJmfOAuth 插件配置中填入相应信息。MetaMask 配置🚧 此平台仍在开发中，配置细节待定。通常涉及请求用户签名以验证钱包所有权。Slack 配置访问 Slack API 应用管理。点击 Create New App → 选择 From scratch。输入应用名称并选择您的 Slack 工作区。在左侧菜单导航至 OAuth & Permissions。在 Redirect URLs 部分，添加 https://yourdomain.com/oauth/slack/callback。向下滚动到 Scopes → User Token Scopes，添加以下权限:identity.basicidentity.email导航回 Basic Information，找到 App Credentials 部分。记录 Client ID 和 Client Secret。在 IDCsmart 的 ZJmfOAuth 插件配置中填入相应信息。Atlassian 配置访问 Atlassian 开发者控制台。点击 Create → OAuth 2.0 (3LO) integration。输入应用名称。在 APIs and permissions 部分，点击 Add 添加 Jira Platform REST API，并配置所需权限 (例如 View user profile)。根据需要可能还需添加 Confluence 等其他产品的 API。确保至少包含读取用户基本信息的权限。注意：根据插件实现，可能需要 read:jira-user 或类似权限，以及 offline_access (如果需要刷新令牌)。请参考插件的具体要求。在 Authorization 部分，点击 Configure，添加回调 URL: https://yourdomain.com/oauth/atlassian/callback。导航至 Settings 页面，记录 Client ID 和 Secret。在 IDCsmart 的 ZJmfOAuth 插件配置中填入相应信息。🚀 使用示例(此部分应包含插件启用后，用户在登录界面看到的效果截图，或简要说明如何触发 OAuth 登录流程)例如：用户访问 IDCsmart 登录页面。在登录框下方或旁边，会显示已启用的 OAuth 提供商按钮 (如 "使用 Google 登录", "使用 GitHub 登录")。用户点击相应的按钮，将被重定向到对应平台的授权页面。用户在第三方平台授权后，将被重定向回 IDCsmart 并自动登录或关联账户。🚨 常见问题排查1. 错误：401 Unauthorized 或类似授权失败提示检查凭据: 确认您在插件配置中填写的 Client ID, Client Secret, API Key, Private Key 等信息是否准确无误，且未过期。核对回调 URL: 确保您在第三方平台（如 Google, GitHub）配置的回调 URL 与插件设置中的 Redirect URI 完全一致，包括 http 或 https=// 协议。检查 API 权限/Scope: 确认您在第三方平台创建应用时，授予了插件所需的最小必要权限 (Scopes)，例如读取用户邮箱、基本信息等。开启调试模式: 查看插件或 IDCsmart 的详细日志，定位具体错误信息。# 示例：查看可能的日志文件 (路径可能不同)
tail -f /path/to/idcsmart/storage/logs/idcsmart.log
tail -f /path/to/idcsmart/storage/logs/oauth.log
2. 问题：重定向循环或 Callback 页面错误清理缓存: 清除浏览器 Cookie 和站点缓存，然后重试。服务器时间: 确保您的服务器系统时间准确同步。OAuth 流程对时间戳敏感。SSL 证书: 验证您的网站 SSL 证书是否有效且正确配置。回调必须使用 https=//。反向代理配置: 如果您使用了 Nginx 或 Apache 等作为反向代理，请确保正确传递了协议头和真实 IP。# Nginx 配置示例
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header Host $host;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
3. 问题：用户授权后无法登录或关联账户邮箱匹配: 检查用户的第三方平台邮箱是否与 IDCsmart 系统中的账户邮箱一致（如果插件逻辑是基于邮箱匹配）。账户冲突: 确认该第三方账户是否已关联到其他 IDCsmart 账户。插件逻辑: 查看插件文档或联系开发者，了解账户关联的具体逻辑和要求。🤝 贡献指南我们欢迎社区贡献！请遵循以下规范：代码风格: 遵循 PSR-12 编码规范。静态分析: 使用 PHPStan 进行代码检查 (推荐 level 8)。单元测试: 核心功能和重要逻辑变更必须包含相应的单元测试。提交信息:使用清晰的 Commit Message，遵循 Conventional Commits 规范。格式: <type>(<scope>): <subject> (例如: feat(google): add support for token refresh)type 可选: feat, fix, docs, style, refactor, test, chore。# 运行测试套件 (如果项目配置了)
composer test

# 运行代码风格检查 (如果项目配置了)
composer lint
🔒 安全声明我们高度重视用户数据的安全。敏感信息加密: 用户授权后获取的 Access Token、Refresh Token 等敏感信息在存储时，会使用 AES-256-CBC 进行加密。传输安全: 所有与第三方 OAuth 提供商的通信均强制使用 TLS 1.3 (或 TLS 1.2) 进行加密传输。数据加密流程示意:graph LR
    A[用户授权数据<br>(如 Access Token)] --> B{AES-256-CBC 加密};
    B --> C[加密后存入数据库];
    D[IDCsmart 与 OAuth 提供商<br>API 通信] --> E{TLS 1.3 加密};
    E --> F[安全数据传输];
安全建议:定期轮换凭据: 定期 (例如每 90 天) 更新您在第三方平台配置的 Client Secret 或 Private Key。启用双因素认证 (2FA): 强烈建议为您的 IDCsmart 管理员账户和第三方开发者账户启用 2FA。最小权限原则: 在第三方平台配置应用时，仅授予插件运行所必需的最小权限范围 (Scopes)。监控日志: 定期检查 IDCsmart 和 ZJmfOAuth 的审计日志，关注异常登录活动。渗透测试: 建议在生产环境部署前，对集成了 OAuth 的系统进行专业的安全渗透测试。📜 许可证本项目基于 MIT License 开源。版权所有 (c) 2025 MaishanInc

特此免费授予任何获得本软件及相关文档文件（以下简称“软件”）副本的人士，无限制地处理本软件的权限，包括但不限于使用、复制、修改、合并、发布、分发、再许可和/或销售软件副本的权利，并允许获得软件的人士这样做，但须符合以下条件：

上述版权声明和本许可声明应包含在所有副本或实质性部分中。

本软件按“原样”提供，不作任何明示或暗示的保证，包括但不限于对适销性、特定用途适用性和非侵权性的保证。在任何情况下，作者或版权持有人均不对任何索赔、损害或其他责任承担任何责任，无论是在合同诉讼、侵权行为还是其他方面，由软件或软件的使用或其他交易引起、产生或与之相关。
💡 提示: 建议在生产环境使用前进行完整的安全评估和测试。📧 技术支持: contact@maishanzero.com | 文档中心 (请替换为实际链接)⭐ Star 历史: 查看项目的 Star 增长趋势 (可嵌入 Star History 图表链接)
