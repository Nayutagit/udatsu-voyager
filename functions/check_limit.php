<?php
// プランごとの制限をチェックする共通関数
include_once __DIR__ . '/../config/plan_limits.php';

/**
 * プランに応じた制限をチェックする
 *
 * @param string $plan 例：'light', 'standard'
 * @param string $key  チェックする項目のキー（例：'max_requests_per_month'）
 * @param int $currentValue 現在の利用回数や件数など
 * @return bool 上限未満なら true（OK）、超えていたら false（NG）
 */
function check_limit($plan, $key, $currentValue) {
  global $plan_limits;

  if (!isset($plan_limits[$plan])) {
    $plan = 'guest'; // 存在しないプランだったら guest 扱い
  }

  $limit = $plan_limits[$plan][$key] ?? 0;

  // -1 = 無制限 → 常にOK、通常は比較でチェック
  if ($limit < 0) return true;

  return ($currentValue < $limit);
}