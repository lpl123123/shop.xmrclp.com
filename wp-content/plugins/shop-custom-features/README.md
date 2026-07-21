# Shop Custom Features

WordPress / WooCommerce / Woodmart 站点定制功能插件。

## 功能概览

### 1. 在线聊天

- 前台右下角浮动聊天窗口
- 访客可发送消息，后台客服可回复
- **后台可编辑任意聊天消息内容**
- 后台路径：**在线聊天 → 聊天消息 / 聊天设置**

### 2. 商家收款码

- 后台 **商家管理** 中添加商家，上传收款码图片（特色图片）
- 在商品编辑页右侧 **绑定商家收款码** 选择商家
- 前台商品详情页自动展示商家名称与收款码

### 3. 自定义金额在线支付

- 使用短代码创建支付页面
- 用户输入金额后预览商品，点击支付进入 WooCommerce 正常结算流程

## 安装步骤

1. 将 `shop-custom-features` 文件夹放入 `wp-content/plugins/`
2. 在 WordPress 后台 **插件** 中启用 **Shop Custom Features**
3. 确保 **WooCommerce** 已启用

## 使用说明

### 聊天功能

1. 进入 **在线聊天 → 聊天设置**，配置标题、欢迎语等
2. 前台右下角出现聊天按钮
3. 在 **在线聊天 → 聊天消息** 中查看会话、回复访客、编辑或删除消息

### 商家收款码

1. 进入 **商家管理 → 添加商家**
2. 填写商家名称，上传收款码图片（特色图片）
3. 编辑商品，在 **绑定商家收款码** 中选择对应商家
4. 保存后前台商品页会显示收款码

### 在线支付页面

1. 新建页面，例如标题「在线支付」
2. 在页面内容中添加短代码：

```
[shop_custom_payment]
```

可选参数：

```
[shop_custom_payment title="在线支付" description="请输入支付金额" min_amount="0.01" max_amount="50000"]
```

3. 发布页面后，用户输入金额点击 **立即支付** 将跳转到 WooCommerce 结算页

## 技术说明

- 聊天消息存储在 `{prefix}scf_chat_messages` 数据表
- 自定义支付使用隐藏的 WooCommerce 虚拟商品，通过购物车动态定价
- 商品页收款码挂载在 `woocommerce_single_product_summary` 钩子（优先级 35）

## 文件结构

```
shop-custom-features/
├── shop-custom-features.php      # 插件入口
├── includes/
│   ├── class-scf-install.php     # 安装与数据表
│   ├── class-scf-chat.php        # 聊天功能
│   ├── class-scf-merchant.php    # 商家收款码
│   └── class-scf-custom-payment.php # 自定义支付
└── assets/
    ├── css/
    └── js/
```
