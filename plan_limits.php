<?php
// Udatsu各プランの制限値定義（秒・件数・機能ON/OFFなど）

$plan_limits = [

  /* ──────────── トライアル ──────────── */
  'trial' => [
    'label'                   => 'トライアルプラン',
    'duration'                => 180,   // 3分
    'daily_uses'              => 1,
    'monthly_post_limit'      => 1,
    'monthly_stack_limit'     => 1,
    'ai_direct_uses_per_day'  => 10,
    'copy'                    => true,
    'edit_posts'              => true,
    'rewrite_enabled'         => true,
    'backup_days'             => 30,
  ],

  /* ───────────── ライト ───────────── */
  'light' => [
    'label'                   => 'ライトプラン',
    'duration'                => 600,   // 10分
    'daily_uses'              => 3,
    'monthly_post_limit'      => 10,
    'monthly_stack_limit'     => 10,
    'ai_direct_uses_per_day'  => 50,
    'copy'                    => true,
    'edit_posts'              => true,
    'rewrite_enabled'         => true,
    'backup_days'             => 30,
  ],

  /* ────────── スタンダード ────────── */
  'standard' => [
    'label'                   => 'スタンダードプラン',
    'duration'                => 900,   // 15分
    'daily_uses'              => 5,
    'monthly_post_limit'      => 50,
    'monthly_stack_limit'     => 50,
    'ai_direct_uses_per_day'  => 100,
    'copy'                    => true,
    'edit_posts'              => true,
    'rewrite_enabled'         => true,
    'backup_days'             => 30,
  ],

  /* ──────────── プレミアム ──────────── */
  'premium' => [
    'label'                   => 'プレミアムプラン',
    'duration'                => 3600,  // 60分
    'daily_uses'              => 10,
    'monthly_post_limit'      => 100,
    'monthly_stack_limit'     => 100,
    'ai_direct_uses_per_day'  => 500,
    'copy'                    => true,
    'edit_posts'              => true,
    'rewrite_enabled'         => true,
    'backup_days'             => 30,
  ],

  /* ───────────── 管理者 ───────────── */
  'admin' => [
    'label'                   => '管理者',
    'duration'                => 3600,
    'daily_uses'              => 0,     // 無制限
    'monthly_post_limit'      => 0,     // 無制限
    'monthly_stack_limit'     => 0,     // 無制限
    'ai_direct_uses_per_day'  => 0,     // 無制限
    'copy'                    => true,
    'edit_posts'              => true,
    'rewrite_enabled'         => true,
    'backup_days'             => 365,
  ],

];
?>