<?php
/**
 * bot.php
 * 思考の分身 AIチャット 画面
 */
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid) || empty($userName) || empty($userPlan)) {
  header('Location: /index.php');
  exit();
}

$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!is_array($posts)) {
  $posts = [];
}

// 有効な投稿件数
$visiblePosts = array_filter($posts, fn($p) => ($p['status'] ?? '') !== '削除済');
$postsCount = count($visiblePosts);

$profileFile = $userDir . $uid . '_profile.json';
$profile = ["display_name" => $userName, "image" => ''];
if (file_exists($profileFile)) {
  $profile = array_merge($profile, json_decode(file_get_contents($profileFile), true) ?: []);
}
$displayName = $profile['display_name'] ?? $userName;
$imagePath = !empty($profile['image']) ? '../uploads/' . $uid . '/' . $profile['image'] : '../img/default-icon.png';
?>
<!DOCTYPE html>
<html lang="ja" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Udatsu | 思考の分身 AIチャット</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <style>
    .chat-container {
      display: flex;
      flex-direction: column;
      height: calc(100vh - var(--header-height) - var(--nav-height) - env(safe-area-inset-bottom) - 16px);
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-card);
      overflow: hidden;
      margin: 0 16px;
      box-shadow: var(--shadow-card);
    }

    .chat-header {
      padding: 12px 16px;
      border-bottom: 1px solid var(--border);
      background: rgba(255, 255, 255, 0.02);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .chat-header-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .status-badge {
      display: flex;
      align-items: center;
      gap: 6px;
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.2);
      padding: 4px 10px;
      border-radius: var(--radius-pill);
    }

    .status-dot {
      width: 6px;
      height: 6px;
      background-color: var(--success);
      border-radius: 50%;
      box-shadow: 0 0 8px var(--success);
    }

    .status-text {
      font-size: 0.72rem;
      color: var(--success);
      font-weight: 600;
    }

    .learning-count {
      font-size: 0.75rem;
      color: var(--text-secondary);
      background: var(--bg-input);
      padding: 4px 8px;
      border-radius: 4px;
      border: 1px solid var(--border);
    }

    .chat-messages {
      flex: 1;
      padding: 16px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 16px;
      scroll-behavior: smooth;
    }

    /* Custom Scrollbar */
    .chat-messages::-webkit-scrollbar {
      width: 6px;
    }
    .chat-messages::-webkit-scrollbar-track {
      background: transparent;
    }
    .chat-messages::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: var(--radius-pill);
    }

    .message-wrapper {
      display: flex;
      flex-direction: column;
      max-width: 85%;
      animation: messageSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes messageSlideIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .message-wrapper.user {
      align-self: flex-end;
    }

    .message-wrapper.assistant {
      align-self: flex-start;
    }

    .message-bubble {
      padding: 12px 16px;
      border-radius: 18px;
      font-size: 0.92rem;
      line-height: 1.5;
      white-space: pre-wrap;
      word-break: break-all;
    }

    .message-wrapper.user .message-bubble {
      background: linear-gradient(135deg, var(--brand) 0%, #E0A800 100%);
      color: var(--text-on-brand);
      border-bottom-right-radius: 4px;
      font-weight: 500;
      box-shadow: 0 4px 12px rgba(252, 200, 0, 0.15);
    }

    .message-wrapper.assistant .message-bubble {
      background: var(--bg-input);
      color: var(--text-primary);
      border-bottom-left-radius: 4px;
      border: 1px solid var(--border);
    }

    .message-bubble p {
      margin: 0 0 8px 0;
    }
    .message-bubble p:last-child {
      margin-bottom: 0;
    }

    .message-wrapper.assistant .message-bubble a {
      color: var(--brand);
      text-decoration: underline;
      font-weight: 600;
      transition: opacity var(--transition);
    }
    .message-wrapper.assistant .message-bubble a:hover {
      opacity: 0.8;
    }

    .message-time {
      font-size: 0.65rem;
      color: var(--text-muted);
      margin-top: 4px;
      align-self: flex-end;
      padding: 0 4px;
    }

    .message-wrapper.assistant .message-time {
      align-self: flex-start;
    }

    .suggested-prompts-container {
      padding: 12px 16px 4px;
    }

    .suggested-prompts-title {
      font-size: 0.75rem;
      color: var(--text-muted);
      margin-bottom: 8px;
      padding-left: 4px;
      font-weight: 600;
    }

    .suggested-prompts {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .prompt-pill {
      background: var(--bg-input);
      border: 1px solid var(--border);
      color: var(--text-secondary);
      padding: 8px 14px;
      border-radius: var(--radius-pill);
      font-size: 0.8rem;
      cursor: pointer;
      transition: all var(--transition);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .prompt-pill:hover {
      border-color: var(--brand);
      color: var(--brand);
      background: var(--brand-dim);
    }

    .chat-input-area {
      padding: 12px 16px;
      border-top: 1px solid var(--border);
      background: rgba(255, 255, 255, 0.01);
    }

    .chat-form {
      display: flex;
      gap: 8px;
      align-items: flex-end;
    }

    .chat-input {
      flex: 1;
      background: var(--bg-input);
      border: 1px solid var(--border);
      border-radius: 20px;
      color: var(--text-primary);
      padding: 10px 16px;
      font-size: 0.92rem;
      resize: none;
      max-height: 100px;
      min-height: 40px;
      outline: none;
      font-family: inherit;
      transition: border-color var(--transition);
      line-height: 1.4;
    }

    .chat-input:focus {
      border-color: var(--brand);
    }

    .chat-submit-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--brand);
      color: var(--text-on-brand);
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: transform var(--transition), background-color var(--transition);
      flex-shrink: 0;
      font-size: 0.95rem;
    }

    .chat-submit-btn:hover {
      transform: scale(1.05);
    }

    .chat-submit-btn:disabled {
      background: var(--border);
      color: var(--text-muted);
      cursor: not-allowed;
      transform: none;
    }

    .typing-indicator {
      display: flex;
      align-items: center;
      gap: 4px;
      padding: 8px 12px;
    }

    .typing-dot {
      width: 6px;
      height: 6px;
      background-color: var(--text-secondary);
      border-radius: 50%;
      animation: typingBounce 1.4s infinite ease-in-out both;
    }

    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }

    @keyframes typingBounce {
      0%, 80%, 100% { transform: scale(0); }
      40% { transform: scale(1); }
    }

    .empty-chat {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      color: var(--text-secondary);
      text-align: center;
      padding: 30px;
      margin: auto 0;
    }

    .empty-chat i {
      font-size: 2.5rem;
      color: var(--brand);
      margin-bottom: 16px;
      filter: drop-shadow(0 0 8px var(--brand-glow));
    }

    .empty-chat h3 {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 6px;
    }

    .empty-chat p {
      font-size: 0.82rem;
      color: var(--text-secondary);
      max-width: 280px;
      line-height: 1.5;
    }
  </style>
</head>
<body>

<!-- ============================================================
     APP HEADER
     ============================================================ -->
<header class="app-header">
  <a href="mypage.php" class="icon-btn" aria-label="戻る" title="マイページに戻る">
    <i class="fas fa-chevron-left"></i>
  </a>
  <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; gap: 8px; color: var(--brand);">
    <i class="fas fa-brain"></i> 思考の分身 AIチャット
  </div>
  <div style="width: 36px;"></div> <!-- Center layout placeholder -->
</header>

<!-- ============================================================
     MAIN FEED
     ============================================================ -->
<main class="app-main" style="padding-top: calc(var(--header-height) + 8px); padding-bottom: calc(var(--nav-height) + 8px);">
  
  <div class="chat-container">
    
    <!-- Chat Header Status -->
    <div class="chat-header">
      <div class="chat-header-left">
        <div class="status-badge">
          <div class="status-dot"></div>
          <span class="status-text">オンライン</span>
        </div>
      </div>
      <div class="learning-count">
        学習データ: <strong><?= $postsCount ?>件</strong>のVJ
      </div>
    </div>

    <!-- Messages Container -->
    <div class="chat-messages" id="chatMessages">
      
      <!-- Initial State (if no messages) -->
      <div class="empty-chat" id="emptyChat">
        <i class="fas fa-brain"></i>
        <h3>あなたの「思考の分身」</h3>
        <p>これまでに蓄積したボイスジャーナルをもとに、あなたの哲学や考え方を模倣したAIです。自分自身と対話してみましょう。</p>
      </div>

    </div>

    <!-- Suggested Prompts -->
    <div class="suggested-prompts-container" id="suggestedPromptsContainer">
      <div class="suggested-prompts-title">分身に質問してみる</div>
      <div class="suggested-prompts">
        <button class="prompt-pill" onclick="sendSuggested('最近、どんなこと考えてる？')">
          <i class="far fa-compass"></i> 最近の関心事は？
        </button>
        <button class="prompt-pill" onclick="sendSuggested('私のビジネス哲学について教えて')">
          <i class="fas fa-lightbulb"></i> ビジネスの哲学は？
        </button>
        <button class="prompt-pill" onclick="sendSuggested('うだつ（Udatsu）のビジョンは何？')">
          <i class="fas fa-rocket"></i> うだつのビジョンは？
        </button>
        <button class="prompt-pill" onclick="sendSuggested('あなたの言葉の癖や口調で自己紹介して')">
          <i class="far fa-user"></i> 自己紹介して
        </button>
      </div>
    </div>

    <!-- Chat Input Area -->
    <div class="chat-input-area">
      <form class="chat-form" id="chatForm" onsubmit="handleChatSubmit(event)">
        <textarea 
          class="chat-input" 
          id="chatInput" 
          placeholder="分身にメッセージを送る..." 
          rows="1"
          oninput="adjustTextareaHeight(this)"
        ></textarea>
        <button class="chat-submit-btn" id="chatSubmitBtn" type="submit" aria-label="送信">
          <i class="fas fa-paper-plane"></i>
        </button>
      </form>
    </div>

  </div>

</main>

<!-- ============================================================
     BOTTOM NAV
     ============================================================ -->
<nav class="app-nav">
  <a href="mypage.php" class="nav-item">
    <i class="fas fa-home"></i>
    <span>ホーム</span>
  </a>
  <a href="timeline.php" class="nav-item">
    <i class="fas fa-compass"></i>
    <span>タイムライン</span>
  </a>
  <a href="../voyager_upload.php" class="nav-item" aria-label="録音">
    <div class="nav-record">
      <i class="fas fa-microphone"></i>
    </div>
  </a>
  <a href="network.php" class="nav-item">
    <i class="fas fa-user-friends"></i>
    <span>つながり</span>
  </a>
  <a href="profile.php" class="nav-item">
    <i class="fas fa-user"></i>
    <span>自分</span>
  </a>
</nav>

<script>
/* =============================================================
   Theme setting (load-only)
   ============================================================= */
(function() {
  const saved = localStorage.getItem('udatsu_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
})();

/* =============================================================
   Chat Logic
   ============================================================= */
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const chatSubmitBtn = document.getElementById('chatSubmitBtn');
const emptyChat = document.getElementById('emptyChat');
const suggestedPromptsContainer = document.getElementById('suggestedPromptsContainer');

let conversationHistory = []; // Keep history in format: { role: 'user' | 'model', text: string }

function adjustTextareaHeight(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

function appendMessage(role, text) {
  // Remove empty state if present
  if (emptyChat) {
    emptyChat.style.display = 'none';
  }

  const wrapper = document.createElement('div');
  wrapper.className = `message-wrapper ${role === 'user' ? 'user' : 'assistant'}`;

  const bubble = document.createElement('div');
  bubble.className = 'message-bubble';
  if (role === 'assistant') {
    // Parse markdown (e.g. for citations)
    bubble.innerHTML = marked.parse(text);
  } else {
    bubble.textContent = text;
  }
  
  const time = document.createElement('div');
  time.className = 'message-time';
  const now = new Date();
  time.textContent = `${now.getHours()}:${String(now.getMinutes()).padStart(2, '0')}`;

  wrapper.appendChild(bubble);
  wrapper.appendChild(time);
  chatMessages.appendChild(wrapper);
  
  // Scroll to bottom
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function appendTypingIndicator() {
  const wrapper = document.createElement('div');
  wrapper.className = 'message-wrapper assistant';
  wrapper.id = 'typingIndicatorWrapper';

  const bubble = document.createElement('div');
  bubble.className = 'message-bubble';
  bubble.style.padding = '8px 12px';
  
  const indicator = document.createElement('div');
  indicator.className = 'typing-indicator';
  indicator.innerHTML = `
    <div class="typing-dot"></div>
    <div class="typing-dot"></div>
    <div class="typing-dot"></div>
  `;

  bubble.appendChild(indicator);
  wrapper.appendChild(bubble);
  chatMessages.appendChild(wrapper);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function removeTypingIndicator() {
  const indicator = document.getElementById('typingIndicatorWrapper');
  if (indicator) {
    indicator.remove();
  }
}

function appendSystemNotice(text) {
  // Remove empty state if present
  if (emptyChat) {
    emptyChat.style.display = 'none';
  }

  const wrapper = document.createElement('div');
  wrapper.className = 'message-wrapper system';
  wrapper.style.alignSelf = 'center';
  wrapper.style.maxWidth = '90%';
  wrapper.style.margin = '8px 0';
  wrapper.style.animation = 'messageSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards';
  
  const bubble = document.createElement('div');
  bubble.className = 'message-bubble system-notice';
  bubble.style.background = 'rgba(252, 200, 0, 0.04)';
  bubble.style.border = '1px dashed rgba(252, 200, 0, 0.3)';
  bubble.style.borderRadius = '12px';
  bubble.style.padding = '8px 16px';
  bubble.style.fontSize = '0.8rem';
  bubble.style.color = 'var(--brand)';
  bubble.style.display = 'flex';
  bubble.style.alignItems = 'center';
  bubble.style.gap = '8px';
  bubble.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
  
  bubble.innerHTML = `<i class="fas fa-info-circle"></i> <span>${text}</span>`;
  
  wrapper.appendChild(bubble);
  chatMessages.appendChild(wrapper);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function sendSuggested(text) {
  chatInput.value = text;
  document.getElementById('chatForm').requestSubmit();
}

function handleChatSubmit(event) {
  event.preventDefault();
  const text = chatInput.value.trim();
  if (!text) return;

  // Clear input and restore height
  chatInput.value = '';
  chatInput.style.height = '40px';
  
  // Hide suggested prompts after first message
  if (suggestedPromptsContainer) {
    suggestedPromptsContainer.style.display = 'none';
  }

  // Disable UI during response generation
  chatInput.disabled = true;
  chatSubmitBtn.disabled = true;

  // Append user message
  appendMessage('user', text);

  // Show typing indicator
  appendTypingIndicator();

  // Create form data
  const fd = new FormData();
  fd.append('message', text);
  fd.append('history', JSON.stringify(conversationHistory));

  fetch('api_chat.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(data => {
    removeTypingIndicator();
    if (data.status === 'ok') {
      const reply = data.reply;
      appendMessage('assistant', reply);
      
      // Render system notice on post update or delete
      if (data.action === 'update_post') {
        appendSystemNotice(`[システム] ジャーナル「${data.post_title || '無題'}」を修正しました。`);
      } else if (data.action === 'delete_post') {
        appendSystemNotice(`[システム] ジャーナル「${data.post_title || '無題'}」を非公開（削除済）に設定しました。`);
      }
      
      // Update history
      conversationHistory.push({ role: 'user', text: text });
      conversationHistory.push({ role: 'model', text: reply });
      
      // Keep history size small (last 6 exchanges = 12 items)
      if (conversationHistory.length > 12) {
        conversationHistory = conversationHistory.slice(-12);
      }
    } else {
      appendMessage('assistant', `⚠ エラー: ${data.message || '不明なエラーが発生しました。'}`);
    }
  })
  .catch(err => {
    removeTypingIndicator();
    appendMessage('assistant', '⚠ 通信エラーが発生しました。接続を確認して再試行してください。');
    console.error(err);
  })
  .finally(() => {
    chatInput.disabled = false;
    chatSubmitBtn.disabled = false;
    chatInput.focus();
  });
}
</script>

</body>
</html>
