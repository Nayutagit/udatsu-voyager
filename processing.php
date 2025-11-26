<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>解析中 | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Page specific styles */
    body {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    .container {
      position: relative;
      z-index: 1;
      max-width: 800px;
      padding: 2rem;
      text-align: center;
    }
    .analysis-stream {
      position: relative;
      width: 120px;
      height: 300px;
      margin: 3rem auto;
      animation: fadeInScale 1s ease 0.9s both;
    }
    .scan-line {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
      animation: scanDown 2s ease-in-out infinite;
      z-index: 10;
    }
    .upload-stream {
      position: absolute;
      top: 0;
      left: 50%;
      width: 100%;
      height: 100%;
      transform: translateX(-50%);
      pointer-events: none;
    }
    .arrow {
      position: absolute;
      left: 50%;
      width: 0;
      height: 0;
      transform: translateX(-50%);
      filter: drop-shadow(0 0 15px var(--neon-gold-glow));
      border-left: 20px solid transparent;
      border-right: 20px solid transparent;
      border-bottom: 30px solid var(--primary-neon);
      bottom: -30px;
      animation: arrowUp 5s ease-out infinite;
    }
    .arrow:nth-child(1) { animation-delay: 0s; }
    .arrow:nth-child(2) { animation-delay: 0.3s; }
    .arrow:nth-child(3) { animation-delay: 0.6s; }
    .arrow:nth-child(4) { animation-delay: 0.9s; }
    .arrow:nth-child(5) { animation-delay: 1.2s; }
    
    @keyframes scanDown {
      0% { top: 0; opacity: 0; }
      10% { opacity: 1; }
      90% { opacity: 1; }
      100% { top: 100%; opacity: 0; }
    }
    @keyframes arrowUp {
      0% {
        bottom: -30px;
        opacity: 0;
        transform: translateX(-50%) scale(0.6);
      }
      6% {
        opacity: 1;
        transform: translateX(-50%) scale(1);
      }
      34% {
        opacity: 1;
        transform: translateX(-50%) scale(1.1);
      }
      40% {
        bottom: 120%;
        opacity: 0;
        transform: translateX(-50%) scale(0.8);
      }
      100% {
        bottom: -30px;
        opacity: 0;
      }
    }
    @keyframes fadeInScale {
      from { transform: scale(0.8); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    @keyframes textBlink {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }
    #tip-message {
      font-size: 1.2rem;
      font-weight: 500;
      margin-top: 3rem;
      line-height: 1.6;
      color: var(--text-white);
      min-height: 3rem;
      animation: fadeInUp 1s ease 1.2s both, textBlink 2s ease-in-out infinite;
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      padding: 1.5rem 2rem;
      border-radius: 4px;
      border: 1px solid var(--accent-teal);
      box-shadow: 0 0 20px rgba(0, 104, 136, 0.3);
      max-width: 600px;
      margin: 3rem auto 0;
    }
  </style>
  <script>
  // 自動遷移（3.5秒後に analyze.php）
  window.addEventListener("DOMContentLoaded", () => {
    setTimeout(() => {
      window.location.href = "analyze.php";
    }, 3500);
    
    // 段階的なメッセージ変化（演出）
    const tip = document.getElementById("tip-message");
    const steps = [
      "🎧 音声ファイルを受け取りました…",
      "✍️ 文字起こし中です…",
      "✍️ 文字起こし中です…",
      "✍️ 文字起こし中です…",
      "✍️ 文字起こし中です…",
      "🧠 整文を準備しています…"
    ];
    let i = 0;
    const interval = setInterval(() => {
      if (i < steps.length) {
        tip.innerHTML = steps[i++];
      } else {
        clearInterval(interval);
      }
    }, 1000);
  });
  </script>
</head>
<body>
<div class="container">
  <a href="https://udatsuageteko.com" target="_blank" class="logo-link">
    <img src="img/udatsu-logo.png" alt="Udatsuロゴ" class="logo">
  </a>
  
  <h1>Udatsu Voyager</h1>
  <div class="subtitle">音声解析中です・・・</div>
  
  <div class="analysis-stream">
    <div class="scan-line"></div>
    <div class="upload-stream">
      <div class="arrow"></div>
      <div class="arrow"></div>
      <div class="arrow"></div>
      <div class="arrow"></div>
      <div class="arrow"></div>
    </div>
  </div>
  
  <p id="tip-message">
    🎧 音声を受け取りました。解析を開始します…
  </p>
  
  <div class="logout-wrapper" style="margin-top: 3rem;">
    <a href="index.php" class="logout-link">ログアウトしてTOPに戻る</a>
  </div>
  
  <div class="footer-note">
    ※この画面を閉じると処理が中断される場合があります。
  </div>
</div>
</body>
</html>