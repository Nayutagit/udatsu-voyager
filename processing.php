<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Analyzing... | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      overflow: hidden;
    }
    
    .loader-ring {
      position: relative;
      width: 150px;
      height: 150px;
      margin: 0 auto 40px;
    }

    .loader-ring::before, .loader-ring::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      border: 4px solid transparent;
    }

    .loader-ring::before {
      top: 0; left: 0; right: 0; bottom: 0;
      border-top-color: var(--primary-neon);
      border-bottom-color: var(--primary-neon);
      animation: spin 2s linear infinite;
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.2);
    }

    .loader-ring::after {
      top: 10px; left: 10px; right: 10px; bottom: 10px;
      border-left-color: var(--accent-teal);
      border-right-color: var(--accent-teal);
      animation: spin-reverse 1.5s linear infinite;
    }

    .loader-icon {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      font-size: 3rem;
      color: var(--text-white);
      animation: pulse 1s ease-in-out infinite alternate;
    }

    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @keyframes spin-reverse { 0% { transform: rotate(0deg); } 100% { transform: rotate(-360deg); } }
    @keyframes pulse { from { opacity: 0.5; transform: translate(-50%, -50%) scale(0.9); } to { opacity: 1; transform: translate(-50%, -50%) scale(1.1); } }

    .status-text {
      font-family: var(--font-mono);
      font-size: 1.2rem;
      color: var(--primary-neon);
      text-shadow: 0 0 10px rgba(252, 200, 0, 0.5);
      margin-bottom: 10px;
    }

    .progress-bar {
      width: 300px;
      height: 4px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 2px;
      margin: 20px auto;
      overflow: hidden;
      position: relative;
    }

    .progress-fill {
      position: absolute;
      top: 0; left: 0; height: 100%;
      background: var(--accent-teal);
      width: 0%;
      box-shadow: 0 0 10px var(--accent-teal);
      transition: width 0.5s ease;
    }
  </style>
  <script>
  window.addEventListener("DOMContentLoaded", () => {
    const fill = document.querySelector('.progress-fill');
    const status = document.getElementById('status-text');
    
    status.textContent = "AIに音声を送信中...";
    fill.style.width = '50%';

    // Fire the async task and DO NOT wait for it to finish!
    fetch('analyze.php').catch(e => console.error(e));

    // Wait just a tiny bit for the UI effect, then redirect to Dashboard
    setTimeout(() => {
        fill.style.width = '100%';
        status.textContent = "バックグラウンド移行完了";
        setTimeout(() => {
            window.location.href = "mypage/mypage.php?upload_success=1";
        }, 800);
    }, 1000);
  });
  </script>
</head>
<body>

  <div class="container" style="text-align: center;">
    
    <div class="loader-ring">
      <i class="fas fa-brain loader-icon"></i>
    </div>

    <div id="status-text" class="status-text">Initializing...</div>
    
    <div class="progress-bar">
      <div class="progress-fill"></div>
    </div>

    <p class="text-muted" style="margin-top: 20px; font-size: 0.9rem;">
      <i class="fas fa-info-circle"></i> 解析をダッシュボードの裏側に移行しています...
    </p>

  </div>

</body>
</html>