<?php
// プランごとの制限値（秒・回数・投稿上限・掲載リクエスト上限）
$plan_limits = [
  'guest'     => ['duration' => 180,  'daily_uses' => 1, 'post_limit' => 0,  'submit_limit' => 0],
  'trial'     => ['duration' => 180,  'daily_uses' => 1, 'post_limit' => 1,  'submit_limit' => 0],
  'light'     => ['duration' => 600,  'daily_uses' => 3, 'post_limit' => 5,  'submit_limit' => 3],
  'standard'  => ['duration' => 900,  'daily_uses' => 5, 'post_limit' => 10, 'submit_limit' => 5],
  'premium'   => ['duration' => 3600, 'daily_uses' => 10,'post_limit' => 999,'submit_limit' => 30],
  'admin'     => ['duration' => 86400,'daily_uses' => 9999, 'post_limit' => 9999, 'submit_limit' => 9999],
];