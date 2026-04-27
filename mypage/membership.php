<?php
session_start();
$plan = $_SESSION['user_plan'] ?? 'trial';
$user_name = $_SESSION['user_name'] ?? 'ゲスト';

$plan_label = match ($plan) {
  'trial'     => 'トライアルプラン',
  'light'     => 'ライトプラン',
  'standard'  => 'スタンダードプラン',
  'premium'   => 'プレミアムプラン',
  'admin'     => '管理者',
  default     => '不明',
};
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Udatsu メンバーシップ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../css/style.css">
  <script src="https://js.stripe.com/v3/"></script>
  <style>
    /* Page specific styles */
    body {
      padding: 2rem;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
      position: relative;
    }
    h1 {
      font-family: 'Zen Old Mincho', serif;
      font-size: clamp(2.2rem, 5vw, 3.5rem);
      font-weight: 700;
      text-align: center;
      margin-bottom: 1rem;
      color: var(--text-white);
      animation: slideDown 1s ease;
    }
    @keyframes slideDown {
      from { transform: translateY(-30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .current-status {
      text-align: center;
      font-size: 1.2rem;
      margin-bottom: 3rem;
      color: var(--text-muted);
      animation: fadeInUp 1s ease 0.3s both;
    }
    @keyframes fadeInUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .current-status strong {
      color: var(--primary-neon);
      font-weight: 700;
    }
    .plans {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.5rem;
      margin-bottom: 4rem;
      animation: fadeInUp 1s ease 0.6s both;
    }
    .plan-box {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 4px;
      padding: 2.5rem;
      text-align: center;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
      min-height: 420px;
    }
    .plan-box:hover {
      transform: translateY(-8px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
      border-color: var(--primary-neon);
    }
    .plan-box.highlight {
      border-color: var(--primary-neon);
      background: rgba(42, 42, 46, 0.95);
      box-shadow: 0 0 30px rgba(252, 200, 0, 0.3);
      position: relative;
    }
    .plan-box.highlight::after {
      content: '現在のプラン';
      position: absolute;
      top: -1px;
      right: -1px;
      background: var(--primary-neon);
      color: #000;
      padding: 0.5rem 1rem;
      border-radius: 0 4px 0 4px;
      font-size: 0.8rem;
      font-weight: 700;
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
    }
    .plan-name {
      font-size: 1.6rem;
      font-weight: 700;
      margin-bottom: 1rem;
      color: var(--text-white);
    }
    .plan-price {
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--primary-neon);
      margin-bottom: 2rem;
      text-shadow: 0 0 10px rgba(252, 200, 0, 0.6);
    }
    .plan-features {
      list-style: none;
      padding: 0;
      margin: 0 0 2.5rem;
      flex-grow: 1;
    }
    .plan-features li {
      margin-bottom: 0.8rem;
      padding: 0.5rem 0;
      color: var(--text-muted);
      font-size: 0.95rem;
      line-height: 1.4;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .select-btn {
      background: var(--primary-neon);
      color: #000;
      padding: 1rem 2rem;
      border: none;
      border-radius: 50px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      margin-top: auto;
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.4);
      transition: all 0.3s ease;
      text-align: center;
      line-height: 1.4;
      min-height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .select-btn:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 0 30px rgba(252, 200, 0, 0.6);
    }
    .select-btn.disabled {
      background: #666;
      color: var(--text-muted);
      cursor: default;
      box-shadow: none;
    }
    .select-btn.disabled:hover {
      transform: none;
      box-shadow: none;
    }
    .udarow-info {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      border-left: 6px solid var(--primary-neon);
      padding: 2.5rem;
      border-radius: 4px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      margin-bottom: 3rem;
      animation: fadeInUp 1s ease 0.9s both;
      transition: all 0.4s ease;
    }
    .udarow-info:hover {
      transform: translateY(-3px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    }
    .udarow-info h2 {
      margin-top: 0;
      margin-bottom: 1.5rem;
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--primary-neon);
    }
    .udarow-info p {
      color: var(--text-muted);
      line-height: 1.8;
      font-size: 1.05rem;
    }
    .udarow-info strong {
      color: var(--text-white);
      font-weight: 700;
    }
    .back-link {
      display: block;
      text-align: center;
      margin-top: 3rem;
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      color: var(--text-white);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 50px;
      padding: 1rem 2.5rem;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      display: inline-block;
      margin-left: auto;
      margin-right: auto;
      animation: fadeInUp 1s ease 1.2s both;
    }
    .back-link:hover {
      color: #000;
      background: var(--primary-neon);
      border-color: var(--primary-neon);
      transform: translateY(-3px);
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.4);
    }
    @media (max-width: 1024px) {
      .plans {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (max-width: 768px) {
      body {
        padding: 1rem;
      }
      .plans {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      .plan-box {
        padding: 2rem;
        min-height: auto;
      }
      .udarow-info {
        padding: 1.5rem;
      }
      h1 {
        font-size: 2rem;
      }
      .plan-price {
        font-size: 1.8rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Udatsu メンバーシップ</h1>
    <p class="current-status"><?= htmlspecialchars($user_name) ?> さんの現在のステータス：<strong><?= htmlspecialchars($plan_label) ?></strong></p>

    <div class="plans">
      <?php
        $plans = [
          'trial' => [
            'name' => 'トライアル',
            'price' => '¥0 / 月',
            'features' => [
              '投稿：月1件まで',
              'My Udastack：月1件まで',
              '整文：1日1回 / 最大3分',
              'AIに直接指示：1日10回（GPT-4o）'
            ],
            'price_id' => null
          ],
          'light' => [
            'name' => 'ライト',
            'price' => '¥480 / 月',
            'features' => [
              '投稿：月10件まで',
              'My Udastack：月10件まで',
              '整文：1日3回 / 最大10分',
              'AIに直接指示：1日50回（GPT-4o）'
            ],
            'price_id' => 'price_1ROJF1RoMZhQzJflZGvQHTUB'
          ],
          'standard' => [
            'name' => 'スタンダード',
            'price' => '¥980 / 月',
            'features' => [
              '投稿：月50件まで',
              'My Udastack：月50件まで',
              '整文：1日5回 / 最大15分',
              'AIに直接指示：1日100回（GPT-4o）'
            ],
            'price_id' => 'price_1ROJHqRoMZhQzJflC40Kme0d'
          ],
          'premium' => [
            'name' => 'プレミアム',
            'price' => '¥2,480 / 月',
            'features' => [
              '投稿：月100件まで',
              'My Udastack：月100件まで',
              '整文：1日10回 / 最大60分',
              'AIに直接指示：1日500回（GPT-4o）'
            ],
            'price_id' => 'price_1ROJIVRoMZhQzJfl15JPOqAQ'
          ]
        ];

        foreach ($plans as $key => $p) {
          $highlight = ($plan === $key) ? 'highlight' : '';
          echo "<div class='plan-box {$highlight}'>";
          echo "<div class='plan-name'>{$p['name']}</div>";
          echo "<div class='plan-price'>{$p['price']}</div>";
          echo "<ul class='plan-features'>";
          foreach ($p['features'] as $f) echo "<li>{$f}</li>";
          echo "</ul>";

          if ($plan === $key) {
            echo "<span class='select-btn disabled'>利用中</span>";
          } elseif ($key === 'trial') {
            echo "<a class='select-btn' href='https://udatsuageteko.com/sayonarasamishii'>トライアルに戻す<br>（課金停止）</a>";
          } elseif ($p['price_id']) {
            if (in_array($plan, ['light', 'standard', 'premium'])) {
              echo "<a class='select-btn' href='https://udatsuageteko.com/mottoageteko'>プラン変更申請</a>";
            } else {
              echo "<button class='select-btn' onclick=\"startCheckoutWithPlan('{$p['price_id']}')\">申し込む</button>";
            }
          }

          echo "</div>";
        }
      ?>
    </div>

    <div class="udarow-info">
      <h2>My Udastack（マイ・ウダスタック）とは？</h2>
      <p>
        Udatsuユーザーが、自分の「声と思考」を整文して蓄積していくための<strong>非公開の思考ストック機能</strong>です。<br><br>
        プランに応じて積み上げられる件数が異なり、整文された投稿をいつでも見返したり、AIに「この内容でブログを書いて」と指示したりできます。<br><br>
        自分の発信や内省を積み上げながら、<strong>"声から始まる資産づくり"</strong>を実践していきましょう。
      </p>
    </div>

    <div style="text-align: center;">
      <a href="mypage.php" class="back-link">← マイページに戻る</a>
    </div>
  </div>

  <script>
    const stripe = Stripe("pk_live_51RJlLJRoMZhQzJflGTrCVbq9aiwzNmzrNj3YNTEq3igEOB6zMlgLJ6Nv9XT3LEFirkVPAK1vh5Ff3uwuApDzHynX00ROHX7jzj");
    
    function startCheckoutWithPlan(priceId) {
      stripe.redirectToCheckout({
        lineItems: [{ price: priceId, quantity: 1 }],
        mode: 'subscription',
        successUrl: 'https://udatsu-voyager.com/mypage/success.php',
        cancelUrl: 'https://udatsu-voyager.com/mypage/cancel.php',
      }).then(function (result) {
        if (result.error) {
          alert(result.error.message);
        }
      });
    }

    // エフェクト初期化
    document.addEventListener('DOMContentLoaded', function() {
      // プランカードにスタガーアニメーション
      const planBoxes = document.querySelectorAll('.plan-box');
      planBoxes.forEach((box, index) => {
        box.style.animationDelay = `${0.9 + (index * 0.15)}s`;
        box.classList.add('fade-in-stagger');
      });
    });
  </script>
</body>
</html>