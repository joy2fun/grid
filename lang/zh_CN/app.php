<?php

return [
    // Navigation
    'nav' => [
        'stocks' => '股票',
        'trades' => '交易',
        'grids' => '网格',
        'holdings' => '持仓',
        'price_alerts' => '价格提醒',
        'index_chart' => '指数图表',
    ],

    // Common
    'common' => [
        'all' => '全部',
        'new' => '新建',
        'create' => '创建',
        'edit' => '编辑',
        'delete' => '删除',
        'save' => '保存',
        'cancel' => '取消',
        'confirm' => '确认',
        'back' => '返回',
        'close' => '关闭',
        'refresh' => '刷新',
        'search' => '搜索',
        'filter' => '筛选',
        'actions' => '操作',
        'tools' => '工具',
        'import' => '导入',
        'export' => '导出',
        'backup' => '备份',
        'restore' => '恢复',
        'bulk_import' => '批量导入',
        'settings' => '设置',
        'yes' => '是',
        'no' => '否',
        'active' => '启用',
        'inactive' => '禁用',
    ],

    // Stock
    'stock' => [
        'label' => '股票',
        'code' => '代码',
        'name' => '名称',
        'type' => '类型',
        'type_etf' => 'ETF',
        'type_index' => '指数',
        'peak_value' => '峰值',
        'current_price' => '当前价格',
        'rise_percentage' => '涨幅 %',
        'last_trade' => '最后交易',
        'xirr' => 'XIRR',
    ],

    // Trade
    'trade' => [
        'label' => '交易',
        'stock' => '股票',
        'grid' => '网格',
        'side' => '方向',
        'side_buy' => '买入',
        'side_sell' => '卖出',
        'price' => '价格',
        'quantity' => '数量',
        'executed_at' => '执行时间',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
        'from' => '从',
        'until' => '至',
    ],

    // Grid
    'grid' => [
        'label' => '网格',
        'stock' => '股票',
        'name' => '名称',
        'initial_amount' => '初始金额',
        'grid_interval' => '网格间隔',
        'xirr' => '年化收益率 (XIRR)',
        'total_profit' => '总盈亏',
        'max_cash_required' => '最大占用资金',
        'status' => '状态',
        'trades_count' => '交易次数',
        'final_shares' => '最终持股',
        'cash' => '现金',
        'holdings' => '持仓价值',
        'peak_capital' => '峰值资金需求',
        'annual_return' => '年化收益率',
    ],

    // Holding
    'holding' => [
        'label' => '持仓',
        'stock' => '股票',
        'initial_quantity' => '初始数量',
        'initial_cost' => '初始成本',
        'current_quantity' => '当前数量',
        'average_cost' => '平均成本',
        'total_cost' => '总成本',
        'current_qty' => '当前数量',
        'avg_cost' => '平均成本',
    ],

    // Price Alert
    'price_alert' => [
        'label' => '价格提醒',
        'stock' => '股票',
        'stock_name' => '股票名称',
        'alert_type' => '提醒类型',
        'threshold_type_rise' => '价格上涨 (>= 阈值时提醒)',
        'threshold_type_drop' => '价格下跌 (<= 阈值时提醒)',
        'threshold' => '阈值',
        'threshold_price' => '阈值价格',
        'current_price' => '当前价格',
        'last_notified' => '最后通知',
        'active' => '启用',
        'price_rise' => '价格上涨',
        'price_drop' => '价格下跌',
    ],

    // App Settings
    'app_settings' => [
        'title' => '应用设置',
        'description' => '配置应用偏好设置',
        'language' => '语言',
        'language_helper' => '选择您偏好的语言',
        'enable_notifications' => '启用通知',
        'bark_url' => 'Bark 通知 URL',
        'bark_placeholder' => 'https://api.day.app/your-key/',
        'bark_helper' => '输入你的 Bark 推送通知端点 URL',
        'test_notification' => '测试通知',
        'inactive_threshold' => '非活跃股票阈值 (天)',
        'inactive_helper' => '超过此天数未交易的股票将被视为非活跃',
        'price_change_threshold' => '价格变动阈值 (%)',
        'price_change_helper' => '与上次交易价格相比的价格变动百分比（上涨或下跌），触发通知',
        'api_settings' => 'API & OCR 设置',
        'api_description' => '覆盖外部服务的环境变量',
        'deepseek_key' => 'DeepSeek API 密钥',
        'deepseek_helper' => '覆盖 DEEPSEEK_API_KEY',
        'baidu_token' => '百度 OCR Token',
        'baidu_helper' => '覆盖 BAIDU_OCR_TOKEN',
    ],

    // MCP Settings
    'mcp_settings' => [
        'title' => 'MCP 配置',
        'description' => '模型上下文协议 (MCP) 服务器配置，用于与网格交易集成',
        'config' => 'MCP 服务器配置',
        'config_helper' => '将此配置复制到你的 MCP 客户端设置文件（通常是 .mcp.json 或类似文件）',
        'generate_token' => '生成 MCP 设置',
    ],

    // Widgets
    'widgets' => [
        'significant_price_changes' => '显著价格变动',
        'inactive_stocks' => '非活跃股票',
        'stock_price_trades' => '股票价格与交易',
        'monthly_cash_flow' => '月度现金流',
        'no_significant_changes' => '无显著价格变动',
        'no_inactive_stocks' => '无非活跃股票',
        'all_traded_recently' => '所有股票都在最近 :days 天内交易过',
        'current' => '当前',
        'change_percentage' => '变动 %',
        'days_since' => '距离上次交易',
        'days' => '天',
        'inactive_days' => '非活跃天数',
    ],

    // Index Chart
    'index_chart' => [
        'title' => '指数股票价格历史',
        'time_range' => [
            '3m' => '3个月',
            '6m' => '6个月',
            '12m' => '12个月',
            '18m' => '18个月',
            '2y' => '2年',
            '3y' => '3年',
            '4y' => '4年',
            '5y' => '5年',
            '6y' => '6年',
        ],
    ],

    // Import/Export
    'import_export' => [
        'label' => '导入 / 导出',
        'upload_image' => '上传交易截图',
        'upload_images' => '上传交易截图',
        'image_helper' => '上传交易记录截图以自动解析',
        'parse_with_deepseek' => '使用 DeepSeek 解析',
        'fallback_code' => '备用股票代码',
        'fallback_placeholder' => '例如: 601166',
        'fallback_helper' => '当图片解析失败时，将使用此代码',
        'bulk_import_bg' => '批量导入 (后台)',
        'bulk_desc' => '上传多张交易记录截图，它们将在后台处理',
        'stock_json' => '股票 JSON',
        'backup_file' => '备份 JSON 文件',
        'backup_helper' => '上传交易备份 JSON 文件',
        'raw_json' => '原始 JSON 数据',
        'preview' => '预览',
        'no_data' => '无数据预览',
        'invalid_json' => '无效的 JSON 格式',
    ],

    // Table Columns
    'table' => [
        'peak_percentage' => '峰值 %',
    ],

    // Notifications
    'notifications' => [
        'settings_saved' => '设置已保存',
        'import_completed' => '导入完成',
        'import_failed' => '导入失败',
        'restore_completed' => '恢复完成',
        'restore_failed' => '恢复失败',
        'price_synced' => '价格已同步',
        'test_sent' => '测试通知已发送',
        'invalid_json' => '无效的 JSON',
        'invalid_format' => '格式无效',
        'token_generated' => 'Token 已生成',
        'no_file' => '未上传文件',
        'file_not_found' => '文件未找到',
    ],

    // Actions
    'actions' => [
        'backtest' => '回测',
        'price_chart' => '价格图表',
        'sync_price' => '同步价格',
        'sync_prices' => '同步价格',
        'index_chart' => '指数图表',
        'export_xirr_cashflow' => '导出 XIRR 现金流',
    ],
];
